<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The sections of the monthly operational-control checklist (PG-08.01 / RG-08.01.01): the blocks of
 * day-to-day environmental practices verified during the internal inspection. The declaration order
 * is the order shown on the checklist.
 */
enum OperationalControlSection: string
{
    case WATER = 'water';
    case ENERGY = 'energy';
    case PAPER = 'paper';
    case INK = 'ink';
    case DISCHARGE = 'discharge';
    case EMISSIONS = 'emissions';
    case WEEE = 'weee';
    case OFFICE_WASTE = 'office_waste';

    /**
     * Human-facing section name (Spanish) shown as the checklist block heading.
     *
     * @return string the section label
     */
    public function label(): string
    {
        return match ($this) {
            self::WATER => 'Consumo de agua',
            self::ENERGY => 'Consumo energético',
            self::PAPER => 'Consumo de papel',
            self::INK => 'Consumo de tinta',
            self::DISCHARGE => 'Vertidos',
            self::EMISSIONS => 'Emisiones',
            self::WEEE => 'Residuos RAEE',
            self::OFFICE_WASTE => 'Residuos de oficina',
        };
    }
}
