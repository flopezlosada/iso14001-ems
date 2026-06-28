<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How often a legal requirement's compliance is re-evaluated (PC-06.03, "Frecuencia de
 * evaluación"). Annual is the common case at the centre.
 */
enum EvaluationFrequency: string
{
    case ANNUAL = 'annual';
    case BIANNUAL = 'biannual';
    case QUARTERLY = 'quarterly';
    case MONTHLY = 'monthly';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::ANNUAL => 'Anual',
            self::BIANNUAL => 'Semestral',
            self::QUARTERLY => 'Trimestral',
            self::MONTHLY => 'Mensual',
        };
    }

    /**
     * Number of months between two compliance evaluations, used to derive the next review date from
     * the last one. Every cadence is periodic, so this is always a positive integer.
     *
     * @return int months between reviews
     */
    public function intervalMonths(): int
    {
        return match ($this) {
            self::ANNUAL => 12,
            self::BIANNUAL => 6,
            self::QUARTERLY => 3,
            self::MONTHLY => 1,
        };
    }
}
