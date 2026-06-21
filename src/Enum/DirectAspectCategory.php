<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Category of a direct environmental aspect (PG-06.01 Anexo I). The significance of all four is
 * the sum of frequency + intensity + hazard, except discharges (vertidos), which have no
 * intensity criterion.
 */
enum DirectAspectCategory: string
{
    case CONSUMPTION = 'consumption';
    case EMISSION = 'emission';
    case WASTE = 'waste';
    case DISCHARGE = 'discharge';

    /**
     * Whether this category scores the intensity criterion. Discharges (vertidos) do not.
     *
     * @return bool true for all categories except discharge
     */
    public function usesIntensity(): bool
    {
        return self::DISCHARGE !== $this;
    }

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::CONSUMPTION => 'Consumos',
            self::EMISSION => 'Emisiones',
            self::WASTE => 'Residuos',
            self::DISCHARGE => 'Vertidos',
        };
    }
}
