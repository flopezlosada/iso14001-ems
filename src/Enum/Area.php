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
    case OBJECTIVE = 'objective';
    case INDICATOR = 'indicator';
    case OPERATIONAL_CONTROL = 'operational_control';
    case MANAGEMENT_REVIEW = 'management_review';
    case INTERESTED_PARTY = 'interested_party';
    case DAFO = 'dafo';
    case SYSTEM_AUDIT = 'system_audit';
    case COMMUNICATION = 'communication';

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
            self::OBJECTIVE => 'Objetivos',
            self::INDICATOR => 'Indicadores',
            self::OPERATIONAL_CONTROL => 'Control operacional',
            self::MANAGEMENT_REVIEW => 'Revisión por la dirección',
            self::INTERESTED_PARTY => 'Partes interesadas',
            self::DAFO => 'Análisis DAFO',
            self::SYSTEM_AUDIT => 'Auditorías',
            self::COMMUNICATION => 'Comunicaciones',
        };
    }

    /**
     * Name of the module's index route, so an obligation linked to this area can deep-link to where
     * it is actually filled in. Single source of truth shared by the obligations cockpit and the
     * dashboard worklist.
     *
     * @return string the Symfony route name of the area's index page
     */
    public function indexRoute(): string
    {
        return match ($this) {
            self::CONSUMPTION => 'consumption_index',
            self::NONCONFORMITY => 'non_conformity_index',
            self::WASTE => 'waste_index',
            self::SUPPLIER => 'supplier_index',
            self::TRAINING => 'training_index',
            self::LEGAL_REQUIREMENT => 'legal_requirement_index',
            self::EMERGENCY => 'emergency_drill_index',
            self::ASPECT => 'aspect_index',
            self::RISK_OPPORTUNITY => 'risk_index',
            self::OBJECTIVE => 'objective_index',
            self::INDICATOR => 'indicator_index',
            self::OPERATIONAL_CONTROL => 'operational_control_index',
            self::MANAGEMENT_REVIEW => 'management_review_index',
            self::INTERESTED_PARTY => 'interested_party_index',
            self::DAFO => 'dafo_index',
            self::SYSTEM_AUDIT => 'system_audit_index',
            self::COMMUNICATION => 'communication_index',
        };
    }
}
