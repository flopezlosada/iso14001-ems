<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The medium through which a communication took place ("CANAL" in the real register RG-07.04.00:
 * reuniones, tablón, mail, web, cualquier medio…). PC.04.0 (ISO 14001:2015 §7.4) lists meetings and
 * the ordinary channels (e-mail, telephone, post, notice board, website) as the centre's channels.
 */
enum CommunicationChannel: string
{
    case MEETING = 'meeting';
    case NOTICE_BOARD = 'notice_board';
    case EMAIL = 'email';
    case WEB = 'web';
    case PHONE = 'phone';
    case POSTAL = 'postal';
    case OTHER = 'other';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::MEETING => 'Reunión',
            self::NOTICE_BOARD => 'Tablón',
            self::EMAIL => 'Correo electrónico',
            self::WEB => 'Web',
            self::PHONE => 'Teléfono',
            self::POSTAL => 'Correo postal',
            self::OTHER => 'Otro medio',
        };
    }
}
