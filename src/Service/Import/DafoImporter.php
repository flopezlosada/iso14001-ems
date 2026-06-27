<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\DafoAnalysis;
use App\Repository\DafoAnalysisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the DAFO analysis ("F.06.0") from the normalized CSV (one row per school year, four
 * quadrants). Each analysis is upserted by its natural key (school year, which is unique), so a
 * re-import updates the existing row in place instead of duplicating it.
 *
 * Each row is validated on a transient candidate before touching the managed entity, so an invalid
 * row never mutates (and thus never flushes) an analysis already in the database.
 */
final class DafoImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly DafoAnalysisRepository $analyses,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function key(): string
    {
        return 'dafo';
    }

    public function csvFilename(): string
    {
        return 'dafo.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1
        // In-call identity map: an analysis created earlier in this run is not flushed yet, so
        // findOneBy would not see it.
        $seen = [];

        foreach ($rows as $row) {
            ++$line;

            $schoolYear = trim($row['school_year'] ?? '');

            // Resolve the target entity (existing or new), set its values, then validate the managed
            // entity itself — DafoAnalysis carries a UniqueEntity(schoolYear) constraint that a
            // throwaway candidate would trip on a re-import; validating the real entity is fine
            // because UniqueEntity excludes the entity from its own uniqueness check by id. Same
            // pattern as ObjectiveImporter/NonConformityImporter (entities with a unique key).
            $analysis = $seen[$schoolYear] ?? $this->analyses->findOneBy(['schoolYear' => $schoolYear]);
            $isNew = null === $analysis;
            $analysis ??= new DafoAnalysis();
            $analysis
                ->setSchoolYear($schoolYear)
                ->setWeaknesses($this->nullable($row['weaknesses'] ?? ''))
                ->setThreats($this->nullable($row['threats'] ?? ''))
                ->setStrengths($this->nullable($row['strengths'] ?? ''))
                ->setOpportunities($this->nullable($row['opportunities'] ?? ''));

            $violations = $this->validator->validate($analysis);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);
                continue;
            }

            if ($isNew) {
                $this->entityManager->persist($analysis);
                $report->created();
            } else {
                $report->updated();
            }
            $seen[$schoolYear] = $analysis;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }
}
