<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Category of a direct environmental aspect (PG-06.01 Anexo I). The significance of all four is
 * the sum of frequency + intensity + hazard (RG-06.01.01 Rev 02 added the intensity criterion to
 * discharges/vertidos as well). Each category has its own significance threshold, now configurable
 * in {@see \App\Entity\Settings::thresholdFor()}.
 */
enum DirectAspectCategory: string
{
    case CONSUMPTION = 'consumption';
    case EMISSION = 'emission';
    case WASTE = 'waste';
    case DISCHARGE = 'discharge';

    /**
     * Hazard levels offered for this category (PG-06.01 Anexo I, criterio "Peligrosidad").
     * Discharges (vertidos) only define BAJA/ALTA; the rest use the full Baja/Media/Alta scale.
     *
     * @return list<ScoreLevel> the selectable hazard levels
     */
    public function hazardLevels(): array
    {
        return self::DISCHARGE === $this
            ? [ScoreLevel::LOW, ScoreLevel::HIGH]
            : ScoreLevel::cases();
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
