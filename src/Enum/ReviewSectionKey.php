<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The fixed sections of the management review report (RG-09.03.01), in the order required by
 * PG-09.03.00 §4.2 (ISO 14001:2015 §9.3). Declaration order IS the presentation order.
 *
 * Each section holds free text written/edited by Direction. Some input sections can be
 * pre-filled with a frozen snapshot of another module's data by a matching
 * {@see \App\Service\ManagementReview\SectionSummaryProvider}; whether a section has a provider
 * is not encoded here (Open/Closed) — it is discovered from the registered providers.
 */
enum ReviewSectionKey: string
{
    // --- Inputs (§9.3.2) ---
    case PREVIOUS_ACTIONS = 'previous_actions';
    case CONTEXT_CHANGES = 'context_changes';
    case INTERESTED_PARTIES = 'interested_parties';
    case SIGNIFICANT_ASPECTS = 'significant_aspects';
    case RISKS_OPPORTUNITIES = 'risks_opportunities';
    case OBJECTIVES = 'objectives';
    case NON_CONFORMITIES = 'non_conformities';
    case MONITORING_RESULTS = 'monitoring_results';
    case LEGAL_COMPLIANCE = 'legal_compliance';
    case AUDIT_RESULTS = 'audit_results';
    case RESOURCES = 'resources';
    case INTERESTED_PARTY_COMMS = 'interested_party_comms';
    case IMPROVEMENT_OPPORTUNITIES = 'improvement_opportunities';

    // --- Outputs (§9.3.3) ---
    case CONCLUSIONS = 'conclusions';
    case IMPROVEMENT_DECISIONS = 'improvement_decisions';
    case SYSTEM_CHANGES = 'system_changes';
    case UNMET_OBJECTIVES = 'unmet_objectives';
    case BUSINESS_INTEGRATION = 'business_integration';
    case STRATEGIC_DIRECTION = 'strategic_direction';

    /**
     * Whether this section is an input being reviewed or an output decision (PG-09.03.00 §4.2).
     *
     * @return ReviewSectionGroup the half of the report this section belongs to
     */
    public function group(): ReviewSectionGroup
    {
        return match ($this) {
            self::CONCLUSIONS,
            self::IMPROVEMENT_DECISIONS,
            self::SYSTEM_CHANGES,
            self::UNMET_OBJECTIVES,
            self::BUSINESS_INTEGRATION,
            self::STRATEGIC_DIRECTION => ReviewSectionGroup::OUTPUT,
            default => ReviewSectionGroup::INPUT,
        };
    }

    /**
     * Human-facing label (Spanish, the application's UI language), taken from the wording of the
     * real RG-09.03.01 register.
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PREVIOUS_ACTIONS => 'Estado de las acciones de revisiones anteriores',
            self::CONTEXT_CHANGES => 'Cambios en cuestiones externas e internas (DAFO)',
            self::INTERESTED_PARTIES => 'Cambios en las necesidades y expectativas de las partes interesadas',
            self::SIGNIFICANT_ASPECTS => 'Cambios en aspectos ambientales significativos',
            self::RISKS_OPPORTUNITIES => 'Cambios en riesgos y oportunidades',
            self::OBJECTIVES => 'Objetivos ambientales; grado de consecución',
            self::NON_CONFORMITIES => 'No conformidades y acciones correctivas',
            self::MONITORING_RESULTS => 'Resultados de seguimiento y medición (indicadores)',
            self::LEGAL_COMPLIANCE => 'Cumplimiento de requisitos legales y otros requisitos',
            self::AUDIT_RESULTS => 'Resultados de auditorías',
            self::RESOURCES => 'Adecuación de los recursos',
            self::INTERESTED_PARTY_COMMS => 'Comunicaciones pertinentes de las partes interesadas, incluidas quejas',
            self::IMPROVEMENT_OPPORTUNITIES => 'Oportunidades de mejora continua',
            self::CONCLUSIONS => 'Conclusiones sobre la conveniencia, adecuación y eficacia continuas del SGMA',
            self::IMPROVEMENT_DECISIONS => 'Decisiones relacionadas con las oportunidades de mejora continua',
            self::SYSTEM_CHANGES => 'Decisiones sobre necesidad de cambio en el SGMA, incluidos recursos',
            self::UNMET_OBJECTIVES => 'Objetivos no alcanzados y acciones necesarias',
            self::BUSINESS_INTEGRATION => 'Necesidad de integrar el SGMA en otros procesos de negocio',
            self::STRATEGIC_DIRECTION => 'Implicaciones para la dirección estratégica de la organización',
        };
    }
}
