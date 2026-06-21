<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Scope (ámbito) of a legal requirement, per PC-06.03: the administrative level that issues the
 * legislation.
 */
enum LegalScope: string
{
    case EUROPEAN = 'european';
    case NATIONAL = 'national';
    case REGIONAL = 'regional';
    case LOCAL = 'local';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::EUROPEAN => 'Comunitario',
            self::NATIONAL => 'Estatal',
            self::REGIONAL => 'Autonómico',
            self::LOCAL => 'Local',
        };
    }
}
