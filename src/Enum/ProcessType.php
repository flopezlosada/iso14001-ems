<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Classification of the SGMA process affected by a non-conformity, as enumerated in PC.10.0
 * ("Procesos del SGC: Claves, de Soporte, Estratégicos").
 *
 * Kept as the three process families (not the full list of individual procedures) so the field
 * stays stable while the catalogue of concrete procedures evolves.
 */
enum ProcessType: string
{
    case STRATEGIC = 'strategic';
    case KEY = 'key';
    case SUPPORT = 'support';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::STRATEGIC => 'Estratégico',
            self::KEY => 'Clave',
            self::SUPPORT => 'Soporte',
        };
    }
}
