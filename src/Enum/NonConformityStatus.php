<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle state of a non-conformity (PC.10.0). A non-conformity is opened, treated while its
 * corrective actions are applied, and closed once the evidence confirms the cause was removed.
 *
 * Reopening (when closure evidence is later found insufficient) is modelled as moving back to
 * {@see IN_TREATMENT}, not a separate state, to keep the lifecycle small.
 */
enum NonConformityStatus: string
{
    case OPEN = 'open';
    case IN_TREATMENT = 'in_treatment';
    case CLOSED = 'closed';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierta',
            self::IN_TREATMENT => 'En tratamiento',
            self::CLOSED => 'Cerrada',
        };
    }

    /**
     * CSS badge class for the shared semantic scale: an open non-conformity is the most urgent (red),
     * one in treatment is in progress (amber), a closed one is resolved (green).
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'badge--danger',
            self::IN_TREATMENT => 'badge--warning',
            self::CLOSED => 'badge--success',
        };
    }
}
