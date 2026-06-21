<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Evaluation criterion of a supplier (PC.05 §5.6). It is the single source of truth for the
 * supplier's standing: the approval status ("Aprobado" / "No aprobado") is derived from it, so
 * the two can never become inconsistent.
 *
 * - {@see CAPABLE}: proven supplier, no incidents → approved.
 * - {@see ON_TRIAL}: new supplier in its first-year probation → approved (on trial).
 * - {@see NOT_CAPABLE}: incidents proved it unable to deliver → not approved.
 */
enum SupplierCriterion: string
{
    case CAPABLE = 'capable';
    case ON_TRIAL = 'on_trial';
    case NOT_CAPABLE = 'not_capable';

    /**
     * Whether this criterion means the supplier is approved to purchase from.
     *
     * @return bool true for capable/on-trial, false for not-capable
     */
    public function isApproved(): bool
    {
        return self::NOT_CAPABLE !== $this;
    }

    /**
     * Human-facing approval status derived from the criterion (Spanish).
     *
     * @return string "Aprobado" or "No aprobado"
     */
    public function statusLabel(): string
    {
        return $this->isApproved() ? 'Aprobado' : 'No aprobado';
    }

    /**
     * Human-facing criterion label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::CAPABLE => 'Capacitado',
            self::ON_TRIAL => 'A prueba',
            self::NOT_CAPABLE => 'No capacitado',
        };
    }
}
