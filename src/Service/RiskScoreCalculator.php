<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RiskAssessment;
use App\Enum\RiskCategory;

/**
 * Computes the F.08.0 valuation of a {@see RiskAssessment} (PC.03.0 §5.2): the score is the
 * product of the two factors and the category is the band that score falls into.
 *
 * Rules captured here (single source of truth):
 * - score = probability × impact, each in 1-3, so the score is always one of {1,2,3,4,6,9};
 * - 1-2 → trivial, 3-4 → moderate, 6-9 → critical;
 * - the same bands apply to risks and opportunities (only the wording differs).
 */
final class RiskScoreCalculator
{
    /**
     * Fills the assessment's computed score and category in place.
     *
     * @param RiskAssessment $assessment the assessment to score (mutated)
     */
    public function apply(RiskAssessment $assessment): void
    {
        $score = $assessment->getProbability()->value * $assessment->getImpact()->value;

        $assessment
            ->setScore($score)
            ->setCategory($this->categoryFor($score));
    }

    /**
     * Risk/opportunity category for a given score, per the PC.03.0 §5.2 bands.
     *
     * @param int $score the product probability × impact
     *
     * @return RiskCategory the matching category
     */
    public function categoryFor(int $score): RiskCategory
    {
        return match (true) {
            $score <= 2 => RiskCategory::TRIVIAL,
            $score <= 4 => RiskCategory::MODERATE,
            default => RiskCategory::CRITICAL,
        };
    }
}
