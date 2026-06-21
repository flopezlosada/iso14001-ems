<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The four phases of the Plan-Do-Check-Act cycle, the backbone of every ISO management system.
 *
 * They map onto the centre's own folder structure (00.PLAN / 01.IMPLEMENTACIÓN / 02.VERIFICAR /
 * 03.MEJORA), which is how the staff organise their documents. This is a grouping/navigation
 * dimension, NOT a workflow with gates: obligations from all four phases run in parallel through
 * the year, each with its own cadence. The phase is derived from the ISO chapter, never stored
 * on its own, so the two can never contradict each other (see {@see IsoChapter::phase()}).
 */
enum PdcaPhase: string
{
    case PLAN = 'plan';
    case DO = 'do';
    case CHECK = 'check';
    case ACT = 'act';

    /**
     * The "00".."03" prefix the centre uses for the top-level folders.
     *
     * @return string the two-digit folder code
     */
    public function folderCode(): string
    {
        return match ($this) {
            self::PLAN => '00',
            self::DO => '01',
            self::CHECK => '02',
            self::ACT => '03',
        };
    }

    /**
     * Human-facing name (Spanish, the application's UI language), as the staff name the folders.
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PLAN => 'Planificar',
            self::DO => 'Implementación',
            self::CHECK => 'Verificar',
            self::ACT => 'Mejora',
        };
    }
}
