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
    case TRAINING = 'training';
    case LEGAL_REQUIREMENT = 'legal_requirement';
    case EMERGENCY = 'emergency';
    case ASPECT = 'aspect';
    case RISK_OPPORTUNITY = 'risk_opportunity';

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
            self::TRAINING => 'Formación',
            self::LEGAL_REQUIREMENT => 'Requisitos legales',
            self::EMERGENCY => 'Simulacros',
            self::ASPECT => 'Aspectos ambientales',
            self::RISK_OPPORTUNITY => 'Riesgos y oportunidades',
        };
    }
}
