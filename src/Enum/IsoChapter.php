<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The high-level structure (HLS / Annex SL) clauses of ISO 14001:2015 that carry requirements:
 * chapters 4 to 10. They are the second level of the obligations supra-structure
 * (PdcaPhase → IsoChapter → obligation) and match the "PTO X" tags in the centre's
 * "RELACIÓN DE DOCUMENTOS A CUMPLIMENTAR" register.
 *
 * The {@see PdcaPhase} is derived from the chapter (single source of truth), not stored apart.
 *
 * NOTE: this is intentionally separate from {@see SgmaProcess} (used by indicators), which is a
 * coarser 4-bucket grouping with imprecise naming. Unifying the two is a future refactor; kept
 * apart for now so nothing that already depends on SgmaProcess breaks.
 */
enum IsoChapter: int
{
    case CONTEXT = 4;
    case LEADERSHIP = 5;
    case PLANNING = 6;
    case SUPPORT = 7;
    case OPERATION = 8;
    case PERFORMANCE_EVALUATION = 9;
    case IMPROVEMENT = 10;

    /**
     * The PDCA phase this chapter belongs to (4-6 → Plan, 7-8 → Do, 9 → Check, 10 → Act).
     *
     * @return PdcaPhase the derived phase
     */
    public function phase(): PdcaPhase
    {
        return match ($this) {
            self::CONTEXT, self::LEADERSHIP, self::PLANNING => PdcaPhase::PLAN,
            self::SUPPORT, self::OPERATION => PdcaPhase::DO,
            self::PERFORMANCE_EVALUATION => PdcaPhase::CHECK,
            self::IMPROVEMENT => PdcaPhase::ACT,
        };
    }

    /**
     * Human-facing chapter name (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::CONTEXT => 'Contexto',
            self::LEADERSHIP => 'Liderazgo',
            self::PLANNING => 'Planificación',
            self::SUPPORT => 'Apoyo',
            self::OPERATION => 'Operación',
            self::PERFORMANCE_EVALUATION => 'Evaluación del desempeño',
            self::IMPROVEMENT => 'Mejora',
        };
    }
}
