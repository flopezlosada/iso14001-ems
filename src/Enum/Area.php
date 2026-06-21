<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Functional area of the system over which access (read/write) is granted per role.
 *
 * This catalog grows as each module is built; only areas that have a real module are listed,
 * so the permission matrix never shows knobs for features that do not exist yet.
 */
enum Area: string
{
    case CONSUMPTION = 'consumption';
    case NONCONFORMITY = 'nonconformity';
    case WASTE = 'waste';
    case SUPPLIER = 'supplier';

    /**
     * Human-facing area name (Spanish), used in the permissions matrix.
     */
    public function label(): string
    {
        return match ($this) {
            self::CONSUMPTION => 'Consumos',
            self::NONCONFORMITY => 'No conformidades',
            self::WASTE => 'Residuos',
            self::SUPPLIER => 'Proveedores',
        };
    }
}
