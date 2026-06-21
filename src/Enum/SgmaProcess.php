<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Macro-process of the environmental management system that an indicator belongs to, as grouped
 * in the register F.09.0 (Planificación, Recursos, Evaluación del desempeño, Mejora).
 */
enum SgmaProcess: string
{
    case PLANNING = 'planning';
    case RESOURCES = 'resources';
    case PERFORMANCE_EVALUATION = 'performance_evaluation';
    case IMPROVEMENT = 'improvement';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PLANNING => 'Planificación',
            self::RESOURCES => 'Recursos',
            self::PERFORMANCE_EVALUATION => 'Evaluación del desempeño',
            self::IMPROVEMENT => 'Mejora',
        };
    }
}
