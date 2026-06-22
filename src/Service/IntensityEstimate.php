<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ScoreLevel;

/**
 * Result of estimating a consumption aspect's intensity from its readings: the suggested score and
 * the relative interannual change it came from (e.g. 0.25 = +25% vs the baseline). Immutable; a
 * null estimate (no linked source or no comparable data) is represented by the absence of this
 * object, not by an empty instance.
 */
final readonly class IntensityEstimate
{
    /**
     * @param ScoreLevel $level       the suggested intensity score
     * @param float      $changeRatio relative change vs the baseline (0.25 = +25%, -0.1 = -10%)
     */
    public function __construct(
        public ScoreLevel $level,
        public float $changeRatio,
    ) {
    }

    /**
     * Whether the aspect is trending worse enough to surface as "to watch" before its yearly
     * evaluation is even due. This is exactly the intensity having reached HIGH: both the level and
     * the watch criterion are driven by the same rise threshold, so there is a single source of truth.
     */
    public function isWatchworthy(): bool
    {
        return ScoreLevel::HIGH === $this->level;
    }
}
