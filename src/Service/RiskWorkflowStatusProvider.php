<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DafoAnalysisRepository;
use App\Repository\RiskAssessmentRepository;
use App\Repository\RiskOpportunityRepository;

/**
 * Computes the {@see RiskWorkflowStatus} of an exercise from the existing data, without an N+1: the
 * DAFO existence is one lookup, the item total one count, and every per-valuation signal is derived
 * from a single query that eager-loads each valuation's action plan.
 */
final readonly class RiskWorkflowStatusProvider
{
    public function __construct(
        private RiskOpportunityRepository $risks,
        private RiskAssessmentRepository $assessments,
        private DafoAnalysisRepository $dafo,
    ) {
    }

    /**
     * Builds the workflow status for the given exercise.
     *
     * @param string $exercise the school year, in "YYYY-YYYY" format
     *
     * @return RiskWorkflowStatus the pending-work snapshot for that exercise
     */
    public function for(string $exercise): RiskWorkflowStatus
    {
        $dafo = $this->dafo->findOneBy(['schoolYear' => $exercise]);
        $totalItems = $this->risks->count([]);

        $valuations = $this->assessments->findByExerciseWithActions($exercise);
        // A risk/opportunity has at most one valuation per exercise (unique constraint), so the number
        // of valuations of this exercise is the number of items already valued for it.
        $unvalued = max(0, $totalItems - \count($valuations));

        $unapproved = 0;
        $relevantWithoutActions = 0;
        foreach ($valuations as $valuation) {
            if (null === $valuation->getApprovedBy()) {
                ++$unapproved;
            }
            $category = $valuation->getCategory();
            if (null !== $category && $category->requiresActionPlan() && $valuation->getActions()->isEmpty()) {
                ++$relevantWithoutActions;
            }
        }

        return new RiskWorkflowStatus($exercise, null !== $dafo, $dafo?->getId(), $totalItems, $unvalued, $unapproved, $relevantWithoutActions);
    }
}
