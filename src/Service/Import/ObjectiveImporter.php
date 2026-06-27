<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Objective;
use App\Enum\ObjectiveStatus;
use App\Repository\ObjectiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the general objectives register ("F.07.01") from the normalized CSV.
 *
 * Natural key is the reference (OBJ.NN). The responsible and the related aspect are left unset: the
 * source only records a role acronym (not a user) and no machine-readable aspect link. The school
 * year is taken from the row when present, falling back to the current course for legacy exports.
 */
final class ObjectiveImporter extends AbstractDatasetImporter implements DatasetImporter
{
    /** Course assigned when the source row carries no school year (the historic F.07.01 25/26). */
    private const string DEFAULT_SCHOOL_YEAR = '2025-2026';

    public function __construct(
        private readonly ObjectiveRepository $objectives,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'objectives';
    }

    public function csvFilename(): string
    {
        return 'objectives.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1

        foreach ($rows as $row) {
            ++$line;

            $status = ObjectiveStatus::tryFrom($row['status'] ?? '');
            if (null === $status) {
                $report->reject($line, sprintf('Estado de objetivo desconocido: "%s".', $row['status'] ?? ''), $row);
                continue;
            }

            $reference = trim($row['reference'] ?? '');
            $objective = $this->objectives->findOneBy(['reference' => $reference]);
            $isNew = null === $objective;
            $objective ??= new Objective();

            // Normalise the separator: the source may carry a slash ("2025/2026"); the entity stores
            // the hyphenated form.
            $schoolYear = str_replace('/', '-', trim($row['school_year'] ?? ''));
            $objective->setReference($reference)
                ->setSequence((int) ($row['sequence'] ?? 0))
                ->setSchoolYear('' !== $schoolYear ? $schoolYear : self::DEFAULT_SCHOOL_YEAR)
                ->setDescription(trim($row['description'] ?? ''))
                ->setTargetPeriod($this->nullable($row['target_period'] ?? ''))
                ->setStatus($status);

            $violations = $this->validator->validate($objective);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            if ($isNew) {
                $this->entityManager->persist($objective);
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
