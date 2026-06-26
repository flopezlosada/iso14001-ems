<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ProcessArea;
use App\Entity\RiskAction;
use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Enum\RiskLevel;
use App\Enum\RiskOpportunityType;
use App\Repository\ProcessAreaRepository;
use App\Repository\RiskOpportunityRepository;
use App\Service\RiskScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports the risks-and-opportunities register ("F.08.0") from the normalized CSV (one row per
 * risk/opportunity per school year, carrying its assessment for that year and an optional action).
 *
 * The register has no stable code, so each item is upserted by its natural key (type, description):
 * the same risk recurs verbatim across years, with a fresh assessment per term. The process area is
 * a configurable catalogue, upserted by name (the sheet's free-text "Proceso/ÁREA"); its variants
 * from sheet typos are left for the directora to merge in the UI rather than guessed here. The
 * assessment is upserted in place by (item, exercise) — the entity has a unique (item, exercise), so
 * a delete + re-insert would clash within one flush — and so is its single action by description.
 *
 * Score and category are never read from the sheet (which even disagrees with itself, e.g. a 2×2=4
 * row tagged TRIVIAL): they are (re)computed by {@see RiskScoreCalculator} from the PC.03.0 bands on
 * every row, the single source of truth for the rule — exactly as the assessment form does.
 */
final class RiskImporter extends AbstractDatasetImporter implements DatasetImporter
{
    public function __construct(
        private readonly ProcessAreaRepository $processAreas,
        private readonly RiskOpportunityRepository $risks,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly RiskScoreCalculator $calculator,
    ) {
    }

    public function key(): string
    {
        return 'risks';
    }

    public function csvFilename(): string
    {
        return 'risks.csv';
    }

    public function import(iterable $rows, bool $dryRun): ImportReport
    {
        $report = new ImportReport();
        $line = 1; // header is line 1
        // In-call identity maps: findOneBy cannot see entities not flushed yet, so a process area or
        // risk first seen on an earlier row must be reused from here, not re-created.
        $areaCache = [];
        $riskCache = [];

        foreach ($rows as $row) {
            ++$line;

            $type = RiskOpportunityType::tryFrom(trim($row['type'] ?? ''));
            if (null === $type) {
                $report->reject($line, sprintf('Tipo desconocido: "%s".', $row['type'] ?? ''), $row);
                continue;
            }

            $probability = $this->riskLevel($row['probability'] ?? '');
            $impact = $this->riskLevel($row['impact'] ?? '');
            if (null === $probability || null === $impact) {
                $report->reject($line, 'Probabilidad e impacto deben ser 1, 2 o 3.', $row);
                continue;
            }

            $description = trim($row['description'] ?? '');
            $exercise = trim($row['exercise'] ?? '');
            $areaName = trim($row['process_area'] ?? '');

            $area = $this->resolveArea($areaName, $areaCache, $report, $line, $row);
            if (null === $area) {
                continue; // rejection already recorded
            }

            // Validate on throwaway candidates before touching the managed graph.
            $riskCandidate = (new RiskOpportunity())->setType($type)->setDescription($description)->setProcessArea($area);
            $riskViolations = $this->validator->validate($riskCandidate);
            if (\count($riskViolations) > 0) {
                $report->reject($line, $this->formatViolations($riskViolations), $row);
                continue;
            }
            $assessmentCandidate = (new RiskAssessment())
                ->setRiskOpportunity($riskCandidate)->setExercise($exercise)
                ->setProbability($probability)->setImpact($impact);
            $assessmentViolations = $this->validator->validate($assessmentCandidate);
            if (\count($assessmentViolations) > 0) {
                $report->reject($line, $this->formatViolations($assessmentViolations), $row);
                continue;
            }

            $isNew = false;
            $risk = $this->upsertRisk($type, $description, $area, $riskCache, $isNew);
            $assessment = $this->upsertAssessment($risk, $exercise, $probability, $impact, $row);
            $this->upsertAction($assessment, $row);

            // Counted per risk/opportunity: a new item is "created", a recurring one (typically the
            // same risk seen in a later term) is "updated" as its assessment is added/refreshed.
            $isNew ? $report->created() : $report->updated();
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Resolves the process area by name (cached, then repository, then created), recording a
     * rejection and returning null when the name is empty or invalid.
     *
     * @param array<string, ProcessArea> $cache in-call identity map, keyed by area name
     * @param array<string, string>      $row   the source row, kept with any rejection
     */
    private function resolveArea(string $name, array &$cache, ImportReport $report, int $line, array $row): ?ProcessArea
    {
        if ('' === $name) {
            $report->reject($line, 'Proceso/ÁREA vacío.', $row);

            return null;
        }

        $area = $cache[$name] ?? $this->processAreas->findOneBy(['name' => $name]);
        if (null === $area) {
            $candidate = (new ProcessArea())->setName($name);
            $violations = $this->validator->validate($candidate);
            if (\count($violations) > 0) {
                $report->reject($line, $this->formatViolations($violations), $row);

                return null;
            }
            $area = $candidate;
            $this->entityManager->persist($area);
        }
        $cache[$name] = $area;

        return $area;
    }

    /**
     * Upserts the risk/opportunity by its natural key (type, description), creating it on first
     * sight. The process area is refreshed on every pass (the sheet is the source of record).
     *
     * @param array<string, RiskOpportunity> $cache in-call identity map, keyed by "type|description"
     * @param bool                            $isNew set to true when the item did not exist yet
     */
    private function upsertRisk(RiskOpportunityType $type, string $description, ProcessArea $area, array &$cache, bool &$isNew): RiskOpportunity
    {
        $key = $type->value.'|'.$description;
        $risk = $cache[$key] ?? $this->risks->findOneBy(['type' => $type, 'description' => $description]);
        $isNew = null === $risk;
        if (null === $risk) {
            $risk = (new RiskOpportunity())->setType($type)->setDescription($description);
            $this->entityManager->persist($risk);
        }
        $risk->setProcessArea($area);
        $cache[$key] = $risk;

        return $risk;
    }

    /**
     * Updates the risk's assessment for the exercise in place, or adds a new one, then (re)computes
     * its score and category. Honours the unique (item, exercise) without a delete + re-insert.
     *
     * @param array<string, string> $row
     */
    private function upsertAssessment(RiskOpportunity $risk, string $exercise, RiskLevel $probability, RiskLevel $impact, array $row): RiskAssessment
    {
        $assessment = $this->assessmentForExercise($risk, $exercise);
        $assessment
            ->setProbability($probability)
            ->setImpact($impact)
            ->setJustification($this->nullable($row['justification'] ?? ''));
        $this->calculator->apply($assessment);

        return $assessment;
    }

    /**
     * The risk's assessment for the given exercise (existing one updated in place, or a new one
     * added), honouring the unique (item, exercise) constraint within one flush.
     */
    private function assessmentForExercise(RiskOpportunity $risk, string $exercise): RiskAssessment
    {
        foreach ($risk->getAssessments() as $assessment) {
            if ($assessment->getExercise() === $exercise) {
                return $assessment;
            }
        }

        $assessment = (new RiskAssessment())->setExercise($exercise);
        $risk->addAssessment($assessment);

        return $assessment;
    }

    /**
     * Upserts the assessment's single action by description (in place), or adds it; a blank action
     * cell means the risk was identified with no action plan this term.
     *
     * @param array<string, string> $row
     */
    private function upsertAction(RiskAssessment $assessment, array $row): void
    {
        $description = trim($row['action'] ?? '');
        if ('' === $description) {
            return;
        }

        $action = null;
        foreach ($assessment->getActions() as $existing) {
            if ($existing->getDescription() === $description) {
                $action = $existing;
                break;
            }
        }
        if (null === $action) {
            $action = (new RiskAction())->setDescription($description);
            $assessment->addAction($action);
        }
        $action
            ->setResponsible($this->nullable($row['responsible'] ?? ''))
            ->setDeadline($this->nullable($row['deadline'] ?? ''))
            ->setEfficacy($this->nullable($row['efficacy'] ?? ''))
            ->setEvaluatedAt($this->date($row['evaluated_at'] ?? ''));
    }

    /**
     * Maps a raw 1/2/3 cell to a risk level, or null when it is empty or out of range.
     */
    private function riskLevel(string $value): ?RiskLevel
    {
        $value = trim($value);

        return '' === $value ? null : RiskLevel::tryFrom((int) $value);
    }

    /**
     * Parses a normalized ISO date (yyyy-mm-dd) to an immutable date, or null for a blank cell.
     */
    private function date(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
