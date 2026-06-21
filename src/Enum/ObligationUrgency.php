<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The date-derived urgency of an obligation — the traffic-light of the "Qué toca" view.
 *
 * This is computed from the obligation's review due dates, NOT stored: it is complementary to the
 * manual {@see ObligationStatus}. Event-driven obligations (no fixed cadence) have no due date, so
 * they sit apart in {@see EVENT_DRIVEN} rather than ever showing as overdue.
 */
enum ObligationUrgency: string
{
    case OVERDUE = 'overdue';
    case DUE_SOON = 'due_soon';
    case ON_TRACK = 'on_track';
    case EVENT_DRIVEN = 'event_driven';

    /**
     * Relative severity, so the most urgent cadence of an obligation wins when it has several.
     *
     * @return int higher means more urgent
     */
    public function severity(): int
    {
        return match ($this) {
            self::OVERDUE => 3,
            self::DUE_SOON => 2,
            self::ON_TRACK => 1,
            self::EVENT_DRIVEN => 0,
        };
    }

    /**
     * Whether this urgency outranks the given one.
     *
     * @param self $other the urgency to compare against
     *
     * @return bool true if this one is more urgent
     */
    public function isMoreUrgentThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::OVERDUE => 'Vencido',
            self::DUE_SOON => 'Próximo',
            self::ON_TRACK => 'Al día',
            self::EVENT_DRIVEN => 'Según evento',
        };
    }
}
