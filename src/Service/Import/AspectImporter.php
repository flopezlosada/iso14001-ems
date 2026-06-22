<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\AspectEvaluation;
use App\Entity\EnvironmentalAspect;
use App\Enum\AspectType;
use App\Enum\DirectAspectCategory;
use App\Enum\InfluenceLevel;
use App\Enum\ScoreLevel;
use App\Repository\EnvironmentalAspectRepository;
use App\Service\AspectSignificanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the environmental-aspects register ("RG-06.01.01") from the normalized CSV (one row per
 * aspect, carrying its single current evaluation for the year).
 *
 * The aspect is upserted by name; its evaluation is upserted in place by (aspect, year) — the
 * entity has a unique (aspect, year), so a delete + re-insert would clash within one flush. The
 * three aspect kinds carry different criteria (direct: frequency/intensity/hazard; abnormal:
 * probability/control/severity; indirect: capacity of influence). A criterion cell is only a real
 * level when it is one of the procedure's scores (2/4/6, or 1/2/3 for influence); a "0" or blank
 * cell means "not scored" and maps to null. A row with no scored criterion is catalogued as an
 * aspect with no evaluation (it appears in the register but was not evaluated this cycle).
 *
 * Significance is never read from the sheet: it is (re)computed by {@see AspectSignificanceCalculator}
 * on every row, the single source of truth for the rule — exactly as the evaluation form does.
 */
final class AspectImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly EnvironmentalAspectRepository $aspects,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly AspectSignificanceCalculator $calculator,
    ) {
    }

    public function key(): string
    {
        return 'aspects';
    }

    public function csvFilename(): string
    {
        return 'aspects.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1
        // In-call identity map: a re-listed aspect (should not happen, but cheap to guard) must not
        // be persisted twice, and findOneBy would not see one not flushed yet.
        $seen = [];

        foreach ($rows as $row) {
            ++$line;

            $type = AspectType::tryFrom($row['type'] ?? '');
            if (null === $type) {
                $report->reject($line, sprintf('Tipo de aspecto desconocido: "%s".', $row['type'] ?? ''), $row);
                continue;
            }
            // Category only applies to direct aspects; an empty/invalid cell is simply no category.
            $category = AspectType::DIRECT === $type ? DirectAspectCategory::tryFrom($row['category'] ?? '') : null;
            $name = trim($row['name'] ?? '');
            $year = (int) trim($row['year'] ?? '');

            // Validate name/year on throwaway candidates before touching the managed entities.
            $candidate = (new EnvironmentalAspect())->setName($name)->setType($type)->setCategory($category);
            $aspectViolations = $this->validator->validate($candidate);
            if (\count($aspectViolations) > 0) {
                $report->reject($line, $this->formatViolations($aspectViolations), $row);
                continue;
            }
            $yearViolations = $this->validator->validate((new AspectEvaluation())->setAspect($candidate)->setYear($year));
            if (\count($yearViolations) > 0) {
                $report->reject($line, $this->formatViolations($yearViolations), $row);
                continue;
            }

            $aspect = $seen[$name] ?? $this->aspects->findOneBy(['name' => $name]);
            $isNew = null === $aspect;
            $aspect ??= new EnvironmentalAspect();
            $aspect->setName($name)
                ->setType($type)
                ->setCategory($category)
                ->setUnit($this->nullable($row['unit'] ?? ''))
                ->setAssociatedImpact($this->nullable($row['associated_impact'] ?? ''));
            if ($isNew) {
                // active only on creation: a re-import must not silently reactivate an aspect the
                // directora deactivated in the UI (the sheet carries no active flag).
                $aspect->setActive(true);
                $this->entityManager->persist($aspect);
            }
            $seen[$name] = $aspect;

            if ($this->isEvaluated($type, $row)) {
                $this->upsertEvaluation($aspect, $year, $type, $row);
            }

            $isNew ? $report->created() : $report->updated();
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Whether the row carries a real evaluation for its aspect kind: at least one criterion scored
     * (direct/abnormal) or any capacity-of-influence value present (indirect, where "0" is the
     * meaningful "Nula" score, not a blank).
     *
     * @param array<string, string> $row
     */
    private function isEvaluated(AspectType $type, array $row): bool
    {
        return match ($type) {
            AspectType::DIRECT => $this->anyScored($row, ['frequency', 'intensity', 'hazard']),
            AspectType::ABNORMAL => $this->anyScored($row, ['probability', 'control', 'severity']),
            AspectType::INDIRECT => '' !== trim($row['influence'] ?? ''),
        };
    }

    /**
     * Updates the aspect's evaluation for the year in place, or adds a new one, then (re)computes its
     * significance. The criteria set depends on the aspect kind.
     *
     * @param array<string, string> $row
     */
    private function upsertEvaluation(EnvironmentalAspect $aspect, int $year, AspectType $type, array $row): void
    {
        $evaluation = $this->evaluationForYear($aspect, $year);

        match ($type) {
            AspectType::DIRECT => $evaluation
                ->setFrequency($this->scoreLevel($row['frequency'] ?? ''))
                ->setIntensity($this->scoreLevel($row['intensity'] ?? ''))
                ->setHazard($this->scoreLevel($row['hazard'] ?? '')),
            AspectType::ABNORMAL => $evaluation
                ->setProbability($this->scoreLevel($row['probability'] ?? ''))
                ->setControl($this->scoreLevel($row['control'] ?? ''))
                ->setSeverity($this->scoreLevel($row['severity'] ?? '')),
            AspectType::INDIRECT => $evaluation
                ->setInfluence($this->influenceLevel($row['influence'] ?? '')),
        };
        $evaluation->setNotes($this->nullable($row['notes'] ?? ''));

        $this->calculator->apply($evaluation);
    }

    /**
     * The aspect's evaluation for the given year (existing one updated in place, or a new one added),
     * honouring the unique (aspect, year) constraint without a delete + re-insert within one flush.
     */
    private function evaluationForYear(EnvironmentalAspect $aspect, int $year): AspectEvaluation
    {
        foreach ($aspect->getEvaluations() as $evaluation) {
            if ($evaluation->getYear() === $year) {
                return $evaluation;
            }
        }

        $evaluation = (new AspectEvaluation())->setYear($year);
        $aspect->addEvaluation($evaluation);

        return $evaluation;
    }

    /**
     * Whether any of the given criterion columns holds a real score level (2/4/6); a "0" or blank
     * cell means the criterion was not scored.
     *
     * @param array<string, string> $row
     * @param list<string>          $columns
     */
    private function anyScored(array $row, array $columns): bool
    {
        foreach ($columns as $column) {
            if (null !== $this->scoreLevel($row[$column] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maps a raw criterion cell to a score level, or null when it is not one of the procedure's
     * levels (2 = Baja, 4 = Media, 6 = Alta); a "0" or blank cell is "not scored".
     */
    private function scoreLevel(string $value): ?ScoreLevel
    {
        $value = trim($value);

        return '' === $value ? null : ScoreLevel::tryFrom((int) $value);
    }

    /**
     * Maps a raw capacity-of-influence cell to an influence level, or null for "0" (Nula, which the
     * enum does not model) or a blank cell; the calculator records a 0 significance for those.
     */
    private function influenceLevel(string $value): ?InfluenceLevel
    {
        $value = trim($value);

        return '' === $value ? null : InfluenceLevel::tryFrom((int) $value);
    }
}
