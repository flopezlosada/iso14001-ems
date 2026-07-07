<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AspectEvaluationRepository;
use App\Repository\EnvironmentalAspectRepository;

/**
 * Computes the {@see AspectWorkflowStatus} of a year from the existing data, without an N+1: the
 * active-aspect total is one count and every per-year signal is derived from a single query over
 * that year's evaluations of active aspects.
 */
final readonly class AspectWorkflowStatusProvider
{
    public function __construct(
        private EnvironmentalAspectRepository $aspects,
        private AspectEvaluationRepository $evaluations,
    ) {
    }

    /**
     * Builds the workflow status for the given year.
     *
     * @param int $year the calendar year to snapshot
     *
     * @return AspectWorkflowStatus the pending-work snapshot for that year
     */
    public function for(int $year): AspectWorkflowStatus
    {
        $totalActive = $this->aspects->countActive();

        $evaluations = $this->evaluations->findByYearForActiveAspects($year);
        // The unique (aspect, year) constraint means one evaluation per active aspect per year, so
        // the number of this year's evaluations is the number of active aspects already evaluated.
        $unevaluated = max(0, $totalActive - \count($evaluations));

        $significant = 0;
        foreach ($evaluations as $evaluation) {
            if ($evaluation->isSignificant()) {
                ++$significant;
            }
        }

        return new AspectWorkflowStatus($year, $totalActive, $unevaluated, $significant);
    }
}
