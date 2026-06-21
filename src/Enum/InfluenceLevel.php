<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Capacity-of-influence level for indirect aspects (PG-06.01 Anexo II): the centre's ability to
 * influence the agent/provider behind a life-cycle aspect. Scored 1/2/3.
 *
 * Note: the procedure defines no significance threshold for indirect aspects, so significance is
 * marked manually rather than computed.
 */
enum InfluenceLevel: int
{
    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;

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
