<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types of monthly consumption tracked by the environmental management system.
 *
 * Gasoil, electricity, water and paper record both a quantity and a cost; toner and ink
 * cartridges record only the number of units (no cost). The case order is the column order of
 * the official form F-6.1.2, so every listing that iterates {@see self::cases()} matches it.
 */
enum ConsumptionType: string
{
    case GASOIL = 'gasoil';
    case ELECTRICITY = 'electricity';
    case WATER = 'water';
    case PAPER = 'paper';
    case TONER = 'toner';
    case INK_CARTRIDGE = 'ink_cartridge';

    /**
     * Unit of measurement for the recorded quantity.
     *
     * @return string the unit symbol (e.g. "kWh", "m³")
     */
    public function unit(): string
    {
        return match ($this) {
            self::GASOIL => 'L',
            self::ELECTRICITY => 'kWh',
            self::WATER => 'm³',
            self::PAPER => 'paquetes',
            self::TONER, self::INK_CARTRIDGE => 'unidades',
        };
    }

    /**
     * Human-facing label (Spanish, the application's UI language). Single source of truth for
     * the consumption type name, used both in forms and listings.
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::GASOIL => 'Gasoil',
            self::ELECTRICITY => 'Luz',
            self::WATER => 'Agua',
            self::PAPER => 'Papel',
            self::TONER => 'Tóner',
            self::INK_CARTRIDGE => 'Cartuchos de tinta',
        };
    }

    /**
     * Whether a monetary cost (euros) is recorded for this consumption type.
     *
     * @return bool false for the printing consumables (toner and ink cartridges), true otherwise
     */
    public function tracksCost(): bool
    {
        return match ($this) {
            self::TONER, self::INK_CARTRIDGE => false,
            default => true,
        };
    }
}
