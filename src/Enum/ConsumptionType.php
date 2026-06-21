<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types of monthly consumption tracked by the environmental management system.
 *
 * Electricity, water, gasoil and paper record both a quantity and a cost; toner records only
 * the number of cartridges (no cost).
 */
enum ConsumptionType: string
{
    case ELECTRICITY = 'electricity';
    case WATER = 'water';
    case GASOIL = 'gasoil';
    case PAPER = 'paper';
    case TONER = 'toner';

    /**
     * Unit of measurement for the recorded quantity.
     *
     * @return string the unit symbol (e.g. "kWh", "m³")
     */
    public function unit(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'kWh',
            self::WATER => 'm³',
            self::GASOIL => 'L',
            self::PAPER => 'packages',
            self::TONER => 'cartridges',
        };
    }

    /**
     * Whether a monetary cost (euros) is recorded for this consumption type.
     *
     * @return bool true for all types except toner
     */
    public function tracksCost(): bool
    {
        return self::TONER !== $this;
    }
}
