<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whether a communication is internal (between areas/functions of the centre) or external (with
 * parties outside the centre). Mirrors the "INTERNA / EXTERNA" classification of the real register
 * RG-07.04.00 "Comunicaciones externas e internas" (PC.04.0, ISO 14001:2015 §7.4).
 */
enum CommunicationScope: string
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::INTERNAL => 'Interna',
            self::EXTERNAL => 'Externa',
        };
    }
}
