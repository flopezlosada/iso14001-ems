<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Indicator;
use App\Entity\IndicatorMeasurement;
use App\Enum\MeasurementPeriodicity;
use App\Enum\SgmaProcess;
use App\Repository\IndicatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the indicators register ("F.09.0") from the normalized CSV (one row per indicator
 * metadata, plus one row per numeric monthly measurement).
 *
 * The indicator is upserted by name; each measurement is upserted in place by (year, month) — the
 * entity has a unique (indicator, year, month), so a delete + re-insert would clash within one
 * flush. Qualitative readings are not in the CSV (the measurement value is numeric-only).
 */
final class IndicatorImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly IndicatorRepository $indicators,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'indicators';
    }

    public function csvFilename(): string
    {
        return 'indicators.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1

        foreach ($rows as $row) {
            ++$line;

            $process = SgmaProcess::tryFrom($row['process'] ?? '');
            if (null === $process) {
                $report->reject($line, sprintf('Proceso SGMA desconocido: "%s".', $row['process'] ?? ''), $row);
                continue;
            }
            $periodicity = MeasurementPeriodicity::tryFrom($row['periodicity'] ?? '') ?? MeasurementPeriodicity::MONTHLY;
            $name = trim($row['name'] ?? '');

            // Validate name/process on a throwaway candidate before touching the managed indicator.
            $candidate = (new Indicator())->setName($name)->setProcess($process)->setPeriodicity($periodicity);
            $violations = $this->validator->validate($candidate);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            $indicator = $this->indicators->findOneBy(['name' => $name]);
            $isNewIndicator = null === $indicator;
            $indicator ??= new Indicator();
            $indicator->setName($name)
                ->setProcess($process)
                ->setPeriodicity($periodicity)
                ->setReferenceValue($this->nullable($row['reference_value'] ?? ''))
                ->setMeasurementDescription($this->nullable($row['measurement_description'] ?? ''));
            if ($isNewIndicator) {
                $this->entityManager->persist($indicator);
            }

            $year = trim($row['year'] ?? '');
            if ('' === $year) {
                // Metadata-only row: the indicator itself is the unit of work.
                $isNewIndicator ? $report->created() : $report->updated();
                continue;
            }

            $isNewMeasurement = $this->upsertMeasurement($indicator, (int) $year, (int) ($row['month'] ?? 0), trim($row['value'] ?? ''));
            $isNewMeasurement ? $report->created() : $report->updated();
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Updates the indicator's measurement for the given period in place, or adds a new one. Returns
     * true when a new measurement was created, false when an existing one was updated.
     */
    private function upsertMeasurement(Indicator $indicator, int $year, int $month, string $value): bool
    {
        foreach ($indicator->getMeasurements() as $measurement) {
            if ($measurement->getYear() === $year && $measurement->getMonth() === $month) {
                $measurement->setValue($value);

                return false;
            }
        }

        $indicator->addMeasurement((new IndicatorMeasurement())->setYear($year)->setMonth($month)->setValue($value));

        return true;
    }
}
