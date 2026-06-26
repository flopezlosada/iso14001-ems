<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Capacity-of-influence level for indirect aspects (PG-06.01 Anexo II): the centre's ability to
 * influence the agent/provider behind a life-cycle aspect. Scored 0/1/2/3 (RG-06.01.01 Rev 02
 * added the explicit "Nula" level for when there is no capacity to influence at all).
 *
 * Note: the procedure defines no significance threshold for indirect aspects, so significance is
 * marked manually rather than computed.
 */
enum InfluenceLevel: int
{
    case NONE = 0;
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
            self::NONE => 'Nula',
            self::LOW => 'Baja',
            self::MEDIUM => 'Media',
            self::HIGH => 'Alta',
        };
    }
}
