<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The nature of a communication ("TIPO DE COMUNICACIÓN" in the real register RG-07.04.00). Kept as a
 * small, closed set focused on what ISO 14001:2015 §9.3 needs to surface in the management review:
 * complaints from interested parties are called out explicitly, the rest are coarse buckets.
 *
 * {@see COMPLAINT} is the one the management review cares most about ("comunicaciones pertinentes de
 * las partes interesadas, incluidas quejas"), so it is treated specially by
 * {@see \App\Service\ManagementReview\Provider\InterestedPartyCommsSummaryProvider}.
 */
enum CommunicationCategory: string
{
    case COMPLAINT = 'complaint';
    case QUERY = 'query';
    case INFORMATION = 'information';
    case SUGGESTION = 'suggestion';
    case OTHER = 'other';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::COMPLAINT => 'Queja',
            self::QUERY => 'Consulta',
            self::INFORMATION => 'Información',
            self::SUGGESTION => 'Propuesta de mejora',
            self::OTHER => 'Otra',
        };
    }
}
