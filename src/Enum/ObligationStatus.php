<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The manual review state of an obligation, mirroring the "¿REVISADO?" column of the centre's
 * "RELACIÓN DE DOCUMENTOS A CUMPLIMENTAR" register (Hecho / Pendiente / En revisión / No aplica).
 *
 * IMPORTANT: this is the *manual* status the responsible sets, capturing nuance a date cannot
 * (e.g. "done but pending the director's signature"). It is NOT the traffic-light of urgency:
 * whether an obligation is due or overdue is *derived* from its {@see ScheduledAlert} due date,
 * not from this field. The two are complementary.
 */
enum ObligationStatus: string
{
    case PENDING = 'pending';
    case IN_REVIEW = 'in_review';
    case DONE = 'done';
    case NOT_APPLICABLE = 'not_applicable';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::IN_REVIEW => 'En revisión',
            self::DONE => 'Hecho',
            self::NOT_APPLICABLE => 'No aplica',
        };
    }

    /**
     * Whether the obligation needs no further action for the current cycle (done or not applicable).
     *
     * @return bool true when the obligation is settled
     */
    public function isSettled(): bool
    {
        return self::DONE === $this || self::NOT_APPLICABLE === $this;
    }
}
