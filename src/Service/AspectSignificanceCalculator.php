<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AspectEvaluation;
use App\Enum\AspectType;
use App\Enum\ScoreLevel;

/**
 * Computes the significance of an aspect evaluation (PG-06.01), the single source of truth for the
 * rule. The criteria and threshold depend on the aspect type:
 *
 * - DIRECT (Anexo I): frequency + intensity + hazard (each 2/4/6); discharges have no intensity;
 *   a missing intensity counts as 4 ("Media") per the no-prior-year-data default. Significant when
 *   the sum exceeds the PER-CATEGORY threshold (consumos/emisiones 12, residuos 10, vertidos 8),
 *   see {@see DirectAspectCategory::significanceThreshold()}.
 * - ABNORMAL (Anexo III): probability + control + severity (each 2/4/6). Threshold is the single
 *   bound default (10).
 * - INDIRECT (Anexo II): only "capacity of influence" (1/2/3) is recorded; the procedure defines
 *   no threshold, so significance is left as a manual decision (not overwritten here).
 */
final class AspectSignificanceCalculator
{
    /**
     * @param int $threshold significance threshold for abnormal aspects, and fallback for a direct
     *                        aspect with no category set (bound from
     *                        %app.aspect_significance_threshold%)
     */
    public function __construct(private readonly int $threshold)
    {
    }

    /**
     * Fills the evaluation's computed significance score (and, except for indirect aspects, its
     * significant flag) in place.
     *
     * @param AspectEvaluation $evaluation the evaluation to score (mutated)
     */
    public function apply(AspectEvaluation $evaluation): void
    {
        $aspect = $evaluation->getAspect();

        if (AspectType::INDIRECT === $aspect->getType()) {
            // No defined threshold for indirect aspects: record the influence as the score and
            // leave the significant flag to the manual decision captured in the form.
            $influence = $evaluation->getInfluence();
            $evaluation->setSignificanceScore(null !== $influence ? $influence->value : 0);

            return;
        }

        if (AspectType::ABNORMAL === $aspect->getType()) {
            // Abnormal aspects (Anexo III) share a single threshold, the bound default (10).
            $score = $this->abnormalScore($evaluation);
            $evaluation->setSignificanceScore($score)->setSignificant($score > $this->threshold);

            return;
        }

        // Direct aspects (Anexo I): the threshold is per-category (consumos/emisiones 12, residuos
        // 10, vertidos 8). A direct aspect with no category falls back to the bound default.
        $score = $this->directScore($evaluation);
        $threshold = $aspect->getCategory()?->significanceThreshold() ?? $this->threshold;
        $evaluation->setSignificanceScore($score)->setSignificant($score > $threshold);
    }

    /**
     * Significance sum for a direct aspect (frequency + intensity + hazard; no intensity for
     * discharges, missing intensity defaults to 4).
     */
    private function directScore(AspectEvaluation $evaluation): int
    {
        $total = $this->value($evaluation->getFrequency(), 0) + $this->value($evaluation->getHazard(), 0);

        $category = $evaluation->getAspect()->getCategory();
        if (null === $category || $category->usesIntensity()) {
            $total += $this->value($evaluation->getIntensity(), ScoreLevel::MEDIUM->value);
        }

        return $total;
    }

    /**
     * Significance sum for an abnormal aspect (probability + control + severity).
     */
    private function abnormalScore(AspectEvaluation $evaluation): int
    {
        return $this->value($evaluation->getProbability(), 0)
            + $this->value($evaluation->getControl(), 0)
            + $this->value($evaluation->getSeverity(), 0);
    }

    /**
     * Score of a criterion level, or the given default when the level is not set.
     *
     * @param ScoreLevel|null $level    the criterion level
     * @param int             $whenNull score to use when no level is set
     *
     * @return int the criterion score
     */
    private function value(?ScoreLevel $level, int $whenNull): int
    {
        return $level instanceof ScoreLevel ? $level->value : $whenNull;
    }
}
