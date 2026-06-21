<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A single evaluation-criterion level for direct/abnormal aspects (PG-06.01). The backing value
 * IS the score added to the significance sum: Baja=2, Media=4, Alta=6.
 */
enum ScoreLevel: int
{
    case LOW = 2;
    case MEDIUM = 4;
    case HIGH = 6;

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baja',
            self::MEDIUM => 'Media',
            self::HIGH => 'Alta',
        };
    }
}
