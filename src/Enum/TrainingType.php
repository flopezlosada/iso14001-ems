<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whether a training action is delivered in-house or by an external provider (column "EXT/INT"
 * of the annual training plan, form F.03.0).
 */
enum TrainingType: string
{
    case INTERNAL = 'int';
    case EXTERNAL = 'ext';

    /**
     * Human-facing label (Spanish, the application's UI language). Single source of truth for the
     * training type name, used both in forms and listings.
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
