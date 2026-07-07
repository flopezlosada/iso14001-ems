<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Indicator;
use App\Entity\IndicatorMeasurement;
use App\Enum\IndicatorYearStatus;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of the indicator's yearly standing: {@see IndicatorYearStatus} labels/badge
 * classes and the roll-up from a year's measurements in {@see Indicator::statusForYear()}.
 */
final class IndicatorStatusTest extends TestCase
{
    public function testEnumLabelsAndBadgeClasses(): void
    {
        self::assertSame('Sin medir', IndicatorYearStatus::NOT_MEASURED->label());
        self::assertSame('En objetivo', IndicatorYearStatus::ON_TARGET->label());
        self::assertSame('Con desviación', IndicatorYearStatus::BREACHED->label());

        self::assertSame('badge--draft', IndicatorYearStatus::NOT_MEASURED->badgeClass());
        self::assertSame('badge--success', IndicatorYearStatus::ON_TARGET->badgeClass());
        self::assertSame('badge--danger', IndicatorYearStatus::BREACHED->badgeClass());
    }

    public function testNotMeasuredWhenNoMeasurementForTheYear(): void
    {
        $indicator = new Indicator();
        self::assertSame(IndicatorYearStatus::NOT_MEASURED, $indicator->statusForYear(2026));

        // A measurement from another year does not count for the current one.
        $indicator->addMeasurement($this->measurement(2025, 3, '4.000', false));
        self::assertSame(IndicatorYearStatus::NOT_MEASURED, $indicator->statusForYear(2026));
    }

    public function testOnTargetWhenMeasuredAndNoneBreached(): void
    {
        $indicator = new Indicator();
        $indicator->addMeasurement($this->measurement(2026, 1, '3.000', false));
        $indicator->addMeasurement($this->measurement(2026, 2, '4.500', false));

        self::assertSame(IndicatorYearStatus::ON_TARGET, $indicator->statusForYear(2026));
    }

    public function testBreachedWhenAnyMeasurementOfTheYearBreaches(): void
    {
        $indicator = new Indicator();
        $indicator->addMeasurement($this->measurement(2026, 1, '3.000', false));
        $indicator->addMeasurement($this->measurement(2026, 2, '9.000', true));

        self::assertSame(IndicatorYearStatus::BREACHED, $indicator->statusForYear(2026));
    }

    public function testBreachInAnotherYearDoesNotAffectTheCurrentOne(): void
    {
        $indicator = new Indicator();
        $indicator->addMeasurement($this->measurement(2025, 12, '9.000', true));
        $indicator->addMeasurement($this->measurement(2026, 1, '3.000', false));

        self::assertSame(IndicatorYearStatus::ON_TARGET, $indicator->statusForYear(2026));
    }

    private function measurement(int $year, int $month, string $value, bool $breached): IndicatorMeasurement
    {
        return (new IndicatorMeasurement())
            ->setYear($year)
            ->setMonth($month)
            ->setValue($value)
            ->setBreached($breached);
    }
}
