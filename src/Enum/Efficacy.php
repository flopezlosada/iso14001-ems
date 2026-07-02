<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Result of the effectiveness review of a corrective action (PC.10.0 §4.3.4): "OK / NO OK".
 *
 * A null value on the corrective action means the review is still pending; this enum only models
 * the two evaluated outcomes.
 */
enum Efficacy: string
{
    case OK = 'ok';
    case NOT_OK = 'not_ok';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::OK => 'OK',
            self::NOT_OK => 'No OK',
        };
    }

    /**
     * CSS badge class for the shared semantic scale: an effective action is green, an ineffective
     * one red. (A pending review is null and is handled by the caller, not this enum.)
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::OK => 'badge--success',
            self::NOT_OK => 'badge--danger',
        };
    }
}
