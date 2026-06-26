<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The two halves of a management review report (PG-09.03.00 §4.2): the inputs that are reviewed
 * and the outputs (conclusions and decisions) that Direction produces from them.
 */
enum ReviewSectionGroup: string
{
    case INPUT = 'input';
    case OUTPUT = 'output';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::INPUT => 'Elementos de entrada',
            self::OUTPUT => 'Elementos de salida',
        };
    }
}
