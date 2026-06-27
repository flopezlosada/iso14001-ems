<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\YearlyTrendChart;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared multi-year trend chart builder: windowing, scaling to the window max,
 * and the edge cases (empty input, an all-zero window).
 */
final class YearlyTrendChartTest extends TestCase
{
    private YearlyTrendChart $chart;

    protected function setUp(): void
    {
        $this->chart = new YearlyTrendChart();
    }

    public function testReturnsNoPointsForEmptyTotals(): void
    {
        self::assertSame([], $this->chart->points([], 5));
    }

    public function testScalesEachBarToTheWindowMaximum(): void
    {
        $points = $this->chart->points([2024 => '500', 2025 => '1000', 2026 => '250'], 5);

        self::assertSame(
            [
                ['year' => 2024, 'value' => 500.0, 'percent' => 50],
                ['year' => 2025, 'value' => 1000.0, 'percent' => 100],
                ['year' => 2026, 'value' => 250.0, 'percent' => 25],
            ],
            $points,
        );
    }

    public function testKeepsOnlyTheMostRecentYearsAndRescalesToThatWindow(): void
    {
        // The 4000 of 2022 is outside the 2-year window, so the window max is 2026's 2000.
        $points = $this->chart->points([2022 => '4000', 2025 => '1000', 2026 => '2000'], 2);

        self::assertSame(
            [
                ['year' => 2025, 'value' => 1000.0, 'percent' => 50],
                ['year' => 2026, 'value' => 2000.0, 'percent' => 100],
            ],
            $points,
        );
    }

    public function testWindowLargerThanAvailableDataReturnsEveryYear(): void
    {
        // The common case when the system has just a year or two of history: asking for 5 years must
        // simply return what there is, not pad with empty years.
        $points = $this->chart->points([2025 => '100'], 5);

        self::assertSame([['year' => 2025, 'value' => 100.0, 'percent' => 100]], $points);
    }

    public function testAllZeroWindowYieldsZeroPercentNotDivisionByZero(): void
    {
        $points = $this->chart->points([2025 => '0', 2026 => '0'], 5);

        self::assertSame(
            [
                ['year' => 2025, 'value' => 0.0, 'percent' => 0],
                ['year' => 2026, 'value' => 0.0, 'percent' => 0],
            ],
            $points,
        );
    }
}
