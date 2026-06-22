<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The outcome of a single operational-control checklist item (PG-08.01). A null answer (no enum
 * value) means "not assessed yet"; only these two values are valid recorded outcomes.
 */
enum ConformityResult: string
{
    case CONFORME = 'conforme';
    case NON_CONFORME = 'non_conforme';

    /**
     * Human-facing outcome name (Spanish) shown next to each checklist item.
     *
     * @return string the outcome label
     */
    public function label(): string
    {
        return match ($this) {
            self::CONFORME => 'Conforme',
            self::NON_CONFORME => 'No conforme',
        };
    }
}
