<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EnvironmentalAspect;
use App\Enum\ScoreLevel;
use App\Repository\ConsumptionReadingRepository;
use App\Repository\WasteRecordRepository;

/**
 * Suggests the intensity of a direct aspect (PG-06.01) from its real data instead of a manual guess:
 * the year-to-date quantity is compared against a baseline (the mean of the previous N years over the
 * same calendar window) and the relative change drives the score. Works for CONSUMPTION aspects
 * (monthly readings of a utility) and WASTE aspects (kilograms of a set of LER codes).
 *
 * The core ({@see fromQuantities}) is period- and source-agnostic on purpose: it only compares two
 * numbers, so it serves both the yearly evaluation and the mid-year "to watch" check, and tomorrow
 * any other baseline (a fixed reference year, a longer window) without rewriting the rule.
 * {@see estimateFor} is the thin layer that picks the source from the aspect and resolves the periods.
 */
final class AspectIntensityEstimator
{
    public function __construct(
        private readonly ConsumptionReadingRepository $readings,
        private readonly WasteRecordRepository $wasteRecords,
        private readonly SettingsProvider $settings,
    ) {
    }

    /**
     * Estimates the intensity of an aspect as of a given date, comparing its data so far this year
     * against the baseline over the same year-to-date window (so a mid-year check is fair). Picks the
     * source from the aspect: a linked consumption utility, or a set of linked LER codes (waste).
     *
     * @param EnvironmentalAspect $aspect the aspect to estimate
     * @param \DateTimeImmutable  $asOf   the reference date; its year is the year being estimated
     *
     * @return IntensityEstimate|null the estimate, or null when the aspect has no linked source or
     *                                there is no comparable data (no data this year, no prior year,
     *                                or a baseline at zero)
     */
    public function estimateFor(EnvironmentalAspect $aspect, \DateTimeImmutable $asOf): ?IntensityEstimate
    {
        $year = (int) $asOf->format('Y');

        $type = $aspect->getLinkedConsumptionType();
        if (null !== $type) {
            return $this->estimateSeries(
                $year,
                fn (int $y): ?int => $this->readings->lastRecordedMonth($type, $y),
                fn (int $y, int $month): ?string => $this->readings->sumQuantityForYearToDate($type, $y, $month),
            );
        }

        $lerCodes = $aspect->getLinkedLerCodes();
        if ([] !== $lerCodes) {
            return $this->estimateSeries(
                $year,
                fn (int $y): ?int => $this->wasteRecords->lastRecordedMonth($lerCodes, $y),
                fn (int $y, int $month): ?string => $this->wasteRecords->sumKgForYearToDate($lerCodes, $y, $month),
            );
        }

        return null;
    }

    /**
     * Builds the "to watch" list: for each given aspect, the ones already trending worse enough to
     * watch as of the given date.
     *
     * Runs a couple of cheap aggregate queries per aspect. It aggregates per ASPECT, not per source:
     * if several aspects shared the same source the sums would be recomputed. With the current seed
     * (a handful of linked aspects) that is not a meaningful N+1; group by source here if it grows.
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
     * @param float|null $current  the current-window quantity, or null when there is no data
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
     * Resolves the periods of a data source and turns them into an estimate: cut off at the latest
     * month the current year has data for, sum that year-to-date window, and compare against the
     * baseline over the same window. The closures abstract the source (consumption or waste).
     *
     * @param int                         $year          the year being estimated
     * @param \Closure(int): ?int         $lastMonth     latest month with data in a given year
     * @param \Closure(int, int): ?string $sumYearToDate summed quantity up to a month of a given year
     */
    private function estimateSeries(int $year, \Closure $lastMonth, \Closure $sumYearToDate): ?IntensityEstimate
    {
        $cutMonth = $lastMonth($year);
        if (null === $cutMonth) {
            return null;
        }

        $current = $sumYearToDate($year, $cutMonth);

        return $this->fromQuantities(
            null === $current ? null : (float) $current,
            $this->baselineMean($year, $cutMonth, $sumYearToDate),
        );
    }

    /**
     * Mean over the previous N years of the same year-to-date window, ignoring years with no data
     * (so a missing year doesn't drag the baseline to an artificial low).
     *
     * @param int                         $currentYear   the year being evaluated (excluded from the baseline)
     * @param int                         $cutMonth      inclusive cut-off month of the comparison window
     * @param \Closure(int, int): ?string $sumYearToDate summed quantity up to a month of a given year
     *
     * @return float|null the baseline mean, or null when none of the previous years has data
     */
    private function baselineMean(int $currentYear, int $cutMonth, \Closure $sumYearToDate): ?float
    {
        $sums = [];
        $baselineYears = $this->settings->get()->getIntensityBaselineYears();
        for ($i = 1; $i <= $baselineYears; ++$i) {
            $sum = $sumYearToDate($currentYear - $i, $cutMonth);
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
