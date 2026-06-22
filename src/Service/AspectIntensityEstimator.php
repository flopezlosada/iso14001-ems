<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EnvironmentalAspect;
use App\Enum\ConsumptionType;
use App\Enum\ScoreLevel;
use App\Repository\ConsumptionReadingRepository;

/**
 * Suggests the intensity of a direct CONSUMPTION aspect (PG-06.01) from its real readings instead
 * of a manual guess: the year-to-date consumption is compared against a baseline (the mean of the
 * previous N years over the same calendar window) and the relative change drives the score.
 *
 * The core ({@see fromQuantities}) is period-agnostic on purpose: it only compares two numbers, so
 * it serves both the yearly evaluation and the mid-year "to watch" check, and tomorrow any other
 * baseline (a fixed reference year, a longer window) without rewriting the rule. {@see estimateFor}
 * is the thin layer that resolves the periods from the aspect and "today".
 */
final class AspectIntensityEstimator
{
    public function __construct(
        private readonly ConsumptionReadingRepository $readings,
        private readonly SettingsProvider $settings,
    ) {
    }

    /**
     * Estimates the intensity of an aspect as of a given date, comparing its consumption so far this
     * year against the baseline over the same year-to-date window (so a mid-year check is fair).
     *
     * @param EnvironmentalAspect $aspect the aspect to estimate
     * @param \DateTimeImmutable  $asOf   the reference date; its year is the year being estimated
     *
     * @return IntensityEstimate|null the estimate, or null when the aspect has no linked source or
     *                                there is no comparable data (no readings this year, no prior
     *                                year, or a baseline at zero)
     */
    public function estimateFor(EnvironmentalAspect $aspect, \DateTimeImmutable $asOf): ?IntensityEstimate
    {
        $type = $aspect->getLinkedConsumptionType();
        if (null === $type) {
            return null;
        }

        $year = (int) $asOf->format('Y');

        // Cut off at the latest month the current year actually has data for, and apply that same
        // window to the baseline years, so a partial current year is compared like-for-like.
        $cutMonth = $this->readings->lastRecordedMonth($type, $year);
        if (null === $cutMonth) {
            return null;
        }

        $current = $this->readings->sumQuantityForYearToDate($type, $year, $cutMonth);

        return $this->fromQuantities(
            null === $current ? null : (float) $current,
            $this->baselineMean($type, $year, $cutMonth),
        );
    }

    /**
     * Builds the "to watch" list: for each given aspect, the ones already trending worse enough to
     * watch as of the given date.
     *
     * Runs a couple of cheap aggregate queries per aspect. With the current single-utility seed this
     * is a handful of queries, but note it aggregates per ASPECT, not per consumption type: if
     * several aspects link to the same utility the same sums are recomputed. Group by
     * linkedConsumptionType here when the linked set grows (e.g. when residuos are added).
     *
     * @param iterable<EnvironmentalAspect> $aspects the candidate aspects (expected to be linked)
     * @param \DateTimeImmutable            $asOf    the reference date for the comparison window
     *
     * @return list<array{aspect: EnvironmentalAspect, estimate: IntensityEstimate}> the aspects to watch
     */
    public function watchList(iterable $aspects, \DateTimeImmutable $asOf): array
    {
        $watch = [];
        foreach ($aspects as $aspect) {
            $estimate = $this->estimateFor($aspect, $asOf);
            if (null !== $estimate && $estimate->isWatchworthy()) {
                $watch[] = ['aspect' => $aspect, 'estimate' => $estimate];
            }
        }

        return $watch;
    }

    /**
     * Period-agnostic core: turns a current quantity and a baseline into an intensity estimate.
     * A change strictly above +riseThreshold is HIGH, strictly below -dropThreshold is LOW, and
     * anything in between (including the exact thresholds) is MEDIUM.
     *
     * Public on purpose: it is the reusable comparator of the engine, decoupled from how the periods
     * are chosen, so future callers (a fixed reference year, a trend chart) can reuse the rule
     * without going through {@see estimateFor}.
     *
     * @param float|null $current  the current-window quantity, or null when there is no reading
     * @param float|null $baseline the baseline quantity, or null when there is nothing to compare to
     *
     * @return IntensityEstimate|null the estimate, or null when not comparable (missing data or zero baseline)
     */
    public function fromQuantities(?float $current, ?float $baseline): ?IntensityEstimate
    {
        if (null === $current || null === $baseline || abs($baseline) < 1e-9) {
            return null;
        }

        $settings = $this->settings->get();
        $changeRatio = ($current - $baseline) / $baseline;

        $level = match (true) {
            $changeRatio > $settings->getIntensityRiseThreshold() => ScoreLevel::HIGH,
            $changeRatio < -$settings->getIntensityDropThreshold() => ScoreLevel::LOW,
            default => ScoreLevel::MEDIUM,
        };

        return new IntensityEstimate($level, $changeRatio);
    }

    /**
     * Mean consumption of the previous N years over the same year-to-date window, ignoring years
     * with no reading (so a missing year doesn't drag the baseline to an artificial low).
     *
     * @param ConsumptionType $type        the utility
     * @param int             $currentYear the year being evaluated (excluded from the baseline)
     * @param int             $cutMonth    inclusive cut-off month of the comparison window
     *
     * @return float|null the baseline mean, or null when none of the previous years has data
     */
    private function baselineMean(ConsumptionType $type, int $currentYear, int $cutMonth): ?float
    {
        $sums = [];
        $baselineYears = $this->settings->get()->getIntensityBaselineYears();
        for ($i = 1; $i <= $baselineYears; ++$i) {
            $sum = $this->readings->sumQuantityForYearToDate($type, $currentYear - $i, $cutMonth);
            if (null !== $sum) {
                $sums[] = (float) $sum;
            }
        }

        if ([] === $sums) {
            return null;
        }

        return array_sum($sums) / \count($sums);
    }
}
