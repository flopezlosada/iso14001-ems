<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Status of an {@see \App\Entity\Indicator} for a given year, rolled up from that year's
 * measurements. The per-measurement "good" direction is not modelled (the reference value is free
 * text), so the only signal is the manual {@see \App\Entity\IndicatorMeasurement::isBreached()}
 * flag: any breach in the year is a candidate non-conformity.
 *
 * It is not persisted; it is computed by {@see \App\Entity\Indicator::statusForYear()} so the
 * listing shows each indicator's standing for the current period at a glance.
 */
enum IndicatorYearStatus: string
{
    /** No measurement recorded for the year yet. */
    case NOT_MEASURED = 'not_measured';
    /** Measured, and no measurement breached the reference value. */
    case ON_TARGET = 'on_target';
    /** At least one measurement in the year breached the reference value. */
    case BREACHED = 'breached';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::NOT_MEASURED => 'Sin medir',
            self::ON_TARGET => 'En objetivo',
            self::BREACHED => 'Con desviación',
        };
    }

    /**
     * CSS badge class for the shared semantic scale: not measured is the sober pending state (grey),
     * on target is good (green), a breach demands attention (red).
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::NOT_MEASURED => 'badge--draft',
            self::ON_TARGET => 'badge--success',
            self::BREACHED => 'badge--danger',
        };
    }
}
