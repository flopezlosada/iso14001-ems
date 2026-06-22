<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;
use App\Repository\ConsumptionReadingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports monthly consumption readings (form F-6.1.2) from the normalized consumptions CSV.
 *
 * Natural key is (type, year, month): the unique constraint on the entity guarantees one reading
 * per utility and month, so re-importing updates the existing row instead of duplicating it.
 */
final class ConsumptionImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly ConsumptionReadingRepository $readings,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'consumptions';
    }

    public function csvFilename(): string
    {
        return 'consumptions.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1

        foreach ($rows as $row) {
            ++$line;

            $type = ConsumptionType::tryFrom($row['type'] ?? '');
            if (null === $type) {
                $report->reject($line, sprintf('Tipo de consumo desconocido: "%s".', $row['type'] ?? ''), $row);
                continue;
            }

            $year = (int) ($row['period_year'] ?? 0);
            $month = (int) ($row['period_month'] ?? 0);

            $reading = $this->readings->findOneByPeriod($type, $year, $month);
            $isNew = null === $reading;
            $reading ??= new ConsumptionReading();

            $cost = trim($row['cost'] ?? '');
            $notes = trim($row['notes'] ?? '');

            $reading->setType($type)
                ->setPeriodYear($year)
                ->setPeriodMonth($month)
                ->setQuantity(trim($row['quantity'] ?? ''))
                ->setCost('' === $cost ? null : $cost)
                ->setNotes('' === $notes ? null : $notes);

            $violations = $this->validator->validate($reading);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            if ($isNew) {
                $this->entityManager->persist($reading);
                $report->created();
            } else {
                $report->updated();
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }
}
