<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Status of an environmental objective (PG-06.04). Mirrors the values used in the register
 * F.07.01 ("En curso", "No aplica"...). An unmet objective is a candidate for opening a
 * non-conformity, and is carried over to the next cycle.
 */
enum ObjectiveStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case ACHIEVED = 'achieved';
    case NOT_ACHIEVED = 'not_achieved';
    case NOT_APPLICABLE = 'not_applicable';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'En curso',
            self::ACHIEVED => 'Cumplido',
            self::NOT_ACHIEVED => 'No cumplido',
            self::NOT_APPLICABLE => 'No aplica',
        };
    }
}
