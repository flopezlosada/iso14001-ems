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
 * - DIRECT (Anexo I): frequency + intensity + hazard (each 2/4/6), discharges included (RG-06.01.01
 *   Rev 02 added the intensity criterion to discharges); a missing intensity counts as 4 ("Media")
 *   per the no-prior-year-data default. Significant when the sum REACHES OR EXCEEDS the PER-CATEGORY
 *   threshold (configurable in {@see \App\Entity\Settings}).
 * - ABNORMAL (Anexo III): probability + control + severity (each 2/4/6). Threshold is the single
 *   bound default (10).
 * - INDIRECT (Anexo II): only "capacity of influence" (1/2/3) is recorded; the procedure defines
 *   no threshold, so significance is left as a manual decision (not overwritten here).
 */
final class AspectSignificanceCalculator
{
    public function __construct(private readonly SettingsProvider $settings)
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

        $settings = $this->settings->get();

        if (AspectType::ABNORMAL === $aspect->getType()) {
            // Abnormal aspects (Anexo III) use the single configurable abnormal threshold.
            $score = $this->abnormalScore($evaluation);
            $evaluation->setSignificanceScore($score)->setSignificant($score >= $settings->getAbnormalThreshold());

            return;
        }

        // Direct aspects (Anexo I): the threshold is per-category. A direct aspect with no category
        // falls back to the abnormal/default threshold.
        $score = $this->directScore($evaluation);
        $category = $aspect->getCategory();
        $threshold = null !== $category ? $settings->thresholdFor($category) : $settings->getAbnormalThreshold();
        $evaluation->setSignificanceScore($score)->setSignificant($score >= $threshold);
    }

    /**
     * Significance sum for a direct aspect: frequency + intensity + hazard. All four categories
     * (discharges included since RG-06.01.01 Rev 02) score the three criteria; a missing intensity
     * defaults to 4 ("Media") per the no-prior-year-data convention.
     */
    private function directScore(AspectEvaluation $evaluation): int
    {
        return $this->value($evaluation->getFrequency(), 0)
            + $this->value($evaluation->getIntensity(), ScoreLevel::MEDIUM->value)
            + $this->value($evaluation->getHazard(), 0);
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
