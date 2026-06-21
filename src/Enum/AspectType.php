<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kind of environmental aspect (PG-06.01): produced directly by the centre's activity, indirectly
 * (life cycle), or under abnormal/potential situations. Each kind is evaluated with its own
 * criteria. This first module covers {@see DIRECT}; the others arrive in a later iteration.
 */
enum AspectType: string
{
    case DIRECT = 'direct';
    case INDIRECT = 'indirect';
    case ABNORMAL = 'abnormal';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::DIRECT => 'Directo',
            self::INDIRECT => 'Indirecto',
            self::ABNORMAL => 'Anormal/potencial',
        };
    }
}
