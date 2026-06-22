<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\EnvironmentalAspect;
use App\Entity\Settings;
use App\Enum\ConsumptionType;
use App\Enum\ScoreLevel;
use App\Repository\ConsumptionReadingRepository;
use App\Repository\SettingsRepository;
use App\Service\AspectIntensityEstimator;
use App\Service\SettingsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The auto-intensity rule (PG-06.01) is tested in isolation: the period-agnostic core and its
 * threshold boundaries, the period resolution from an aspect (including the like-for-like window of
 * a partial year and the "no data to compare" cases), the baseline averaging over N years, and the
 * cockpit watch-list.
 */
final class AspectIntensityEstimatorTest extends TestCase
{
    private function estimator(?ConsumptionReadingRepository $readings = null, int $baselineYears = 1): AspectIntensityEstimator
    {
        // Settings with the default ±10% bounds and the given baseline window.
        $settings = (new Settings())->setIntensityBaselineYears($baselineYears);
        $settingsRepository = $this->createMock(SettingsRepository::class);
        $settingsRepository->method('findSettings')->willReturn($settings);

        return new AspectIntensityEstimator(
            $readings ?? $this->createMock(ConsumptionReadingRepository::class),
            new SettingsProvider($settingsRepository),
        );
    }

    private function linkedAspect(ConsumptionType $type = ConsumptionType::ELECTRICITY): EnvironmentalAspect
    {
        return (new EnvironmentalAspect())->setLinkedConsumptionType($type);
    }

    /**
     * @return array<string, array{float, float, ScoreLevel, float}>
     */
    public static function provideQuantities(): array
    {
        return [
            // current, baseline, expected level, expected change ratio
            'rise above +10% → HIGH' => [1200.0, 1000.0, ScoreLevel::HIGH, 0.20],
            'drop below -10% → LOW' => [800.0, 1000.0, ScoreLevel::LOW, -0.20],
            'small change → MEDIUM' => [1050.0, 1000.0, ScoreLevel::MEDIUM, 0.05],
            'exactly +10% → MEDIUM (strict)' => [1100.0, 1000.0, ScoreLevel::MEDIUM, 0.10],
            'exactly -10% → MEDIUM (strict)' => [900.0, 1000.0, ScoreLevel::MEDIUM, -0.10],
        ];
    }

    #[DataProvider('provideQuantities')]
    public function testFromQuantitiesMapsChangeToIntensity(float $current, float $baseline, ScoreLevel $expectedLevel, float $expectedRatio): void
    {
        $estimate = $this->estimator()->fromQuantities($current, $baseline);

        self::assertNotNull($estimate);
        self::assertSame($expectedLevel, $estimate->level);
        self::assertEqualsWithDelta($expectedRatio, $estimate->changeRatio, 0.0001);
    }

    public function testNoEstimateWhenDataIsMissingOrBaselineIsZero(): void
    {
        $estimator = $this->estimator();

        self::assertNull($estimator->fromQuantities(null, 1000.0), 'no current reading');
        self::assertNull($estimator->fromQuantities(1000.0, null), 'no baseline');
        self::assertNull($estimator->fromQuantities(1000.0, 0.0), 'zero baseline is not comparable');
    }

    public function testZeroCurrentAgainstNonZeroBaselineIsAFullDrop(): void
    {
        // A genuine zero this year (the repo returns '0', not null) is real data: -100% → LOW.
        $estimate = $this->estimator()->fromQuantities(0.0, 1000.0);

        self::assertNotNull($estimate);
        self::assertSame(ScoreLevel::LOW, $estimate->level);
        self::assertEqualsWithDelta(-1.0, $estimate->changeRatio, 0.0001);
    }

    public function testEstimateForReturnsNullAndTouchesNothingWhenAspectHasNoSource(): void
    {
        $readings = $this->createMock(ConsumptionReadingRepository::class);
        $readings->expects(self::never())->method('lastRecordedMonth');
        $readings->expects(self::never())->method('sumQuantityForYearToDate');

        $estimate = $this->estimator($readings)->estimateFor(new EnvironmentalAspect(), new \DateTimeImmutable('2026-06-15'));

        self::assertNull($estimate);
    }

    public function testEstimateForReturnsNullWhenCurrentYearHasNoReadings(): void
    {
        $readings = $this->createMock(ConsumptionReadingRepository::class);
        $readings->method('lastRecordedMonth')->willReturn(null);
        $readings->expects(self::never())->method('sumQuantityForYearToDate');

        $estimate = $this->estimator($readings)->estimateFor($this->linkedAspect(), new \DateTimeImmutable('2026-06-15'));

        self::assertNull($estimate);
    }

    public function testEstimateForMapsInterannualChangeToIntensity(): void
    {
        $readings = $this->createMock(ConsumptionReadingRepository::class);
        $readings->method('lastRecordedMonth')->willReturn(6);
        $readings->method('sumQuantityForYearToDate')->willReturnCallback(
            static fn (ConsumptionType $type, int $year, int $month): ?string => match ($year) {
                2026 => '1200',
                2025 => '1000',
                default => null,
            },
        );

        $estimate = $this->estimator($readings)->estimateFor($this->linkedAspect(), new \DateTimeImmutable('2026-06-15'));

        self::assertNotNull($estimate);
        self::assertSame(ScoreLevel::HIGH, $estimate->level);
        self::assertEqualsWithDelta(0.20, $estimate->changeRatio, 0.0001);
    }

    public function testPartialYearIsComparedOverTheSameWindowAsTheBaseline(): void
    {
        $readings = $this->createMock(ConsumptionReadingRepository::class);
        // Data only up to May, even though "today" is the end of December: the cut-off must follow
        // the data (month 5), not the calendar, and apply to BOTH years.
        $readings->method('lastRecordedMonth')->willReturn(5);
        $months = [];
        $readings->method('sumQuantityForYearToDate')->willReturnCallback(
            function (ConsumptionType $type, int $year, int $month) use (&$months): string {
                $months[$year] = $month;

                return '500';
            },
        );

        $this->estimator($readings)->estimateFor($this->linkedAspect(), new \DateTimeImmutable('2026-12-31'));

        self::assertSame([2026 => 5, 2025 => 5], $months, 'both years summed over the same Jan–May window');
    }

    public function testBaselineAveragesNYearsIgnoringMissingOnes(): void
    {
        $readings = $this->createMock(ConsumptionReadingRepository::class);
        $readings->method('lastRecordedMonth')->willReturn(6);
        // 2025 is missing: the baseline must be the mean of the years WITH data (just 2024 = 800),
        // not treat the gap as a zero (which would inflate the change).
        $readings->method('sumQuantityForYearToDate')->willReturnCallback(
            static fn (ConsumptionType $type, int $year, int $month): ?string => match ($year) {
                2026 => '1200',
                2025 => null,
                2024 => '800',
                default => null,
            },
        );

        $estimate = $this->estimator($readings, baselineYears: 2)->estimateFor($this->linkedAspect(), new \DateTimeImmutable('2026-06-15'));

        self::assertNotNull($estimate);
        // (1200 - 800) / 800 = +0.5
        self::assertEqualsWithDelta(0.50, $estimate->changeRatio, 0.0001);
        self::assertSame(ScoreLevel::HIGH, $estimate->level);
    }

    public function testWatchListKeepsOnlyAspectsTrendingAboveTheRiseThreshold(): void
    {
        $readings = $this->createMock(ConsumptionReadingRepository::class);
        $readings->method('lastRecordedMonth')->willReturn(6);
        $readings->method('sumQuantityForYearToDate')->willReturnCallback(
            static fn (ConsumptionType $type, int $year, int $month): ?string => match ([$type, $year]) {
                [ConsumptionType::ELECTRICITY, 2026] => '1300', // +30% → watch
                [ConsumptionType::ELECTRICITY, 2025] => '1000',
                [ConsumptionType::WATER, 2026] => '1020',       // +2% → on track
                [ConsumptionType::WATER, 2025] => '1000',
                default => null,
            },
        );

        $rising = $this->linkedAspect(ConsumptionType::ELECTRICITY)->setName('Electricidad');
        $stable = $this->linkedAspect(ConsumptionType::WATER)->setName('Agua');

        $watch = $this->estimator($readings)->watchList([$rising, $stable], new \DateTimeImmutable('2026-06-15'));

        self::assertCount(1, $watch);
        self::assertSame($rising, $watch[0]['aspect']);
        self::assertSame(ScoreLevel::HIGH, $watch[0]['estimate']->level);
    }
}
