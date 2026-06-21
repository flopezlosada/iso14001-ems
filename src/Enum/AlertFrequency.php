<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Review cadence of a document, driving the e-mail alert engine.
 *
 * Covers the review periodicities of the document register:
 * annual, biannual, monthly and event-driven.
 */
enum AlertFrequency: string
{
    case ANNUAL = 'annual';
    case BIANNUAL = 'biannual';
    case MONTHLY = 'monthly';
    case ON_EVENT = 'on_event';

    /**
     * Number of months between two scheduled reviews, or null for event-driven documents
     * that have no fixed cadence.
     *
     * @return int|null months between reviews, null when ON_EVENT
     */
    public function intervalMonths(): ?int
    {
        return match ($this) {
            self::ANNUAL => 12,
            self::BIANNUAL => 6,
            self::MONTHLY => 1,
            self::ON_EVENT => null,
        };
    }
}
