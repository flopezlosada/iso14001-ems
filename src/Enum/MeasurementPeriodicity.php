<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How often an indicator is measured (F.09.0, "Periodicidad"). Monthly and annual are the common
 * cases at the centre.
 */
enum MeasurementPeriodicity: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case BIANNUAL = 'biannual';
    case ANNUAL = 'annual';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensual',
            self::QUARTERLY => 'Trimestral',
            self::BIANNUAL => 'Semestral',
            self::ANNUAL => 'Anual',
        };
    }
}
