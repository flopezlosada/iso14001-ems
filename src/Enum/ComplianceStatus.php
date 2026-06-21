<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Compliance evaluation of a legal requirement (PC-06.03, "Evaluación del cumplimiento"). A
 * non-compliant requirement is a candidate for opening a non-conformity (incumplimiento legal).
 */
enum ComplianceStatus: string
{
    case COMPLIANT = 'compliant';
    case NON_COMPLIANT = 'non_compliant';
    case PENDING = 'pending';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::COMPLIANT => 'Cumple',
            self::NON_COMPLIANT => 'No cumple',
            self::PENDING => 'Pendiente de evaluar',
        };
    }
}
