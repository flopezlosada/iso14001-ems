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
 * The centre restarts the per-course numbering each year ("OBJ.01" appears once per course), so the
 * natural key is (school year, source code), NOT the code alone — otherwise the three courses would
 * collapse onto one another. The globally unique reference (OBJ-NN) is assigned here from a global
 * sequence, exactly as the UI does, so imported and hand-entered objectives share one numbering. The
 * responsible and the related aspect are left unset: the source only records a role acronym (not a
 * user) and no machine-readable aspect link.
 */
final class ObjectiveImporter extends AbstractDatasetImporter implements DatasetImporter
{
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

        // Reserve the global sequence once and increment in memory: nextSequence() reads the DB and
        // would hand the same number to every new objective before the flush, breaking the unique
        // reference (the same reasoning the UI clone-forward applies).
        $sequence = $this->objectives->nextSequence();

        // In-call identity map keyed by (school year, source code): an objective created earlier in
        // this run is not flushed yet, so findOneBy would not see it and a repeated key in the same
        // CSV would be persisted twice, tripping the unique (school_year, source_code) on flush.
        $seen = [];

        foreach ($rows as $row) {
            ++$line;

            $status = ObjectiveStatus::tryFrom($row['status'] ?? '');
            if (null === $status) {
                $report->reject($line, sprintf('Estado de objetivo desconocido: "%s".', $row['status'] ?? ''), $row);
                continue;
            }

            $sourceCode = trim($row['source_code'] ?? '');
            if ('' === $sourceCode) {
                $report->reject($line, 'Falta el código de objetivo (source_code).', $row);
                continue;
            }

            // Normalise the separator: the source may carry a slash ("2024/2025"); the entity stores
            // the hyphenated form. An empty/invalid course is caught by the entity validation below.
            $schoolYear = str_replace('/', '-', trim($row['school_year'] ?? ''));

            // Natural key: the centre's code is only unique within a course, so it must be scoped by
            // the school year. New objectives get a fresh global reference; existing ones keep theirs.
            $key = $schoolYear.'|'.$sourceCode;
            $objective = $seen[$key] ?? $this->objectives->findOneBy(['schoolYear' => $schoolYear, 'sourceCode' => $sourceCode]);
            $isNew = null === $objective;
            if ($isNew) {
                $objective = (new Objective())
                    ->setReference(sprintf('OBJ-%02d', $sequence))
                    ->setSequence($sequence)
                    ->setSchoolYear($schoolYear)
                    ->setSourceCode($sourceCode);
            }

            $objective
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
                ++$sequence;
                $report->created();
            } else {
                $report->updated();
            }
            $seen[$key] = $objective;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }
}
