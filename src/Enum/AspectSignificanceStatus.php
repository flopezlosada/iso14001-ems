<?php

declare(strict_types=1);

namespace App\Enum;

use App\Entity\AspectEvaluation;

/**
 * How an aspect stands for a given year, for the listing at a glance: not yet evaluated, evaluated
 * and non-significant, or evaluated and significant. It centralises the label and the colour of that
 * state so the catalogue reads coherently on the shared semantic scale, instead of ad-hoc inline
 * markup.
 *
 * The significance verdict itself is computed by {@see \App\Service\AspectSignificanceCalculator} on
 * save; this enum only names and colours the result.
 */
enum AspectSignificanceStatus: string
{
    case UNEVALUATED = 'unevaluated';
    case NOT_SIGNIFICANT = 'not_significant';
    case SIGNIFICANT = 'significant';

    /**
     * Resolves the status of an aspect for a year from its evaluation (or lack of it).
     *
     * @param AspectEvaluation|null $evaluation the aspect's evaluation for the year, or null if none
     *
     * @return self the matching status
     */
    public static function forEvaluation(?AspectEvaluation $evaluation): self
    {
        if (null === $evaluation) {
            return self::UNEVALUATED;
        }

        return $evaluation->isSignificant() ? self::SIGNIFICANT : self::NOT_SIGNIFICANT;
    }

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::UNEVALUATED => 'Sin evaluar',
            self::NOT_SIGNIFICANT => 'No significativo',
            self::SIGNIFICANT => 'Significativo',
        };
    }

    /**
     * CSS badge class on the shared semantic scale: a significant aspect is the one that demands
     * attention (amber, it must feed objectives and controls), while an unevaluated one (pending) and
     * a non-significant one (a benign, resolved outcome) both stay muted/sober — only what matters
     * stands out.
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::UNEVALUATED => 'badge--draft',
            self::NOT_SIGNIFICANT => 'badge--done',
            self::SIGNIFICANT => 'badge--warning',
        };
    }
}
