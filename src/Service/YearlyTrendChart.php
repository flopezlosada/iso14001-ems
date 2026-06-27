<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Turns a year-keyed totals map into the points of the minimal multi-year bar chart shared by the
 * exploratory trend views (consumption, waste). Stateless: same input always yields the same points.
 */
final class YearlyTrendChart
{
    /**
     * Builds the bar-chart points for a single series from its yearly totals.
     *
     * Keeps only the most recent $years that actually have data (years with no value are dropped,
     * not drawn as a misleading zero bar) and scales every bar to the maximum of that window, so the
     * chart reflects the recent relative trend rather than an all-time reference.
     *
     * @param array<int, numeric-string|float|int> $totals year => summed quantity, ascending by year
     * @param int                                  $years  how many of the most recent years (with data) to keep
     *
     * @return list<array{year: int, value: float, percent: int}> chart points, oldest first
     */
    public function points(array $totals, int $years): array
    {
        if ([] === $totals) {
            return [];
        }

        $window = \array_slice($totals, -$years, null, true);
        $max = max(array_map('floatval', $window));

        return array_map(
            static fn (int $year, int|float|string $total): array => [
                'year' => $year,
                'value' => (float) $total,
                'percent' => $max > 0.0 ? (int) round((float) $total / $max * 100) : 0,
            ],
            array_keys($window),
            array_values($window),
        );
    }
}
