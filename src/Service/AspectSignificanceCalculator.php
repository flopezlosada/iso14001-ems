<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AspectEvaluation;
use App\Enum\ScoreLevel;

/**
 * Computes the significance of a direct aspect evaluation (PG-06.01 Anexo I): the sum of the
 * criteria scores, and whether it exceeds the significance threshold.
 *
 * Rules captured here (single source of truth):
 * - score = frequency + intensity + hazard (each 2/4/6);
 * - discharges (vertidos) have no intensity criterion, so it is not added;
 * - for categories that use intensity, a missing intensity counts as 4 ("Media"), per the
 *   procedure's default when there is no prior-year data;
 * - the aspect is significant when the score is strictly greater than the threshold (default 10,
 *   set by the directora and configurable via %app.aspect_significance_threshold%).
 */
final class AspectSignificanceCalculator
{
    /**
     * @param int $threshold significance threshold (bound from %app.aspect_significance_threshold%)
     */
    public function __construct(private readonly int $threshold)
    {
    }

    /**
     * Fills the evaluation's computed significance score and flag in place.
     *
     * @param AspectEvaluation $evaluation the evaluation to score (mutated)
     */
    public function apply(AspectEvaluation $evaluation): void
    {
        $score = $this->score($evaluation);
        $evaluation
            ->setSignificanceScore($score)
            ->setSignificant($score > $this->threshold);
    }

    /**
     * Significance sum for the evaluation, honouring the per-category criteria.
     *
     * @param AspectEvaluation $evaluation the evaluation
     *
     * @return int the significance score
     */
    private function score(AspectEvaluation $evaluation): int
    {
        $total = $this->value($evaluation->getFrequency(), 0) + $this->value($evaluation->getHazard(), 0);

        $category = $evaluation->getAspect()->getCategory();
        if (null === $category || $category->usesIntensity()) {
            // Missing intensity defaults to "Media" (4): the procedure's rule when there is no
            // prior-year data for consumption/waste.
            $total += $this->value($evaluation->getIntensity(), ScoreLevel::MEDIUM->value);
        }

        return $total;
    }

    /**
     * Score of a criterion level, or the given default when the level is not set.
     *
     * @param ScoreLevel|null $level   the criterion level
     * @param int             $whenNull score to use when no level is set
     *
     * @return int the criterion score
     */
    private function value(?ScoreLevel $level, int $whenNull): int
    {
        return $level instanceof ScoreLevel ? $level->value : $whenNull;
    }
}
