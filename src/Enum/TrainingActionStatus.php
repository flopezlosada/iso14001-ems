<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Progress state of a training action of the annual plan (F.03.0), derived from the row's own
 * fields rather than stored: the lifecycle is planned → executed → evaluated, and a row imported
 * with un-normalizable data waits for a human to fix it first.
 *
 * It is not persisted (there is no state column and no state-changing CTA); it is computed by
 * {@see \App\Entity\TrainingAction::status()} so the listing and the detail page share one rule.
 */
enum TrainingActionStatus: string
{
    /** Imported with data the centre still has to verify by hand (ETL could not normalize it). */
    case NEEDS_REVIEW = 'needs_review';
    /** Planned but not yet delivered (no actual date recorded). */
    case PLANNED = 'planned';
    /** Delivered but its effectiveness has not been evaluated yet. */
    case EXECUTED = 'executed';
    /** Delivered and its effectiveness evaluated: nothing left to do. */
    case EVALUATED = 'evaluated';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::NEEDS_REVIEW => 'Revisar',
            self::PLANNED => 'Planificada',
            self::EXECUTED => 'Pendiente de evaluar',
            self::EVALUATED => 'Evaluada',
        };
    }

    /**
     * CSS badge class for the shared semantic scale: a planned action is the sober starting point
     * (grey), one delivered awaiting its effectiveness evaluation needs attention (amber), an
     * evaluated one is done (green); a row flagged for review is a data-quality alert (review).
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::NEEDS_REVIEW => 'badge--review',
            self::PLANNED => 'badge--draft',
            self::EXECUTED => 'badge--warning',
            self::EVALUATED => 'badge--success',
        };
    }
}
