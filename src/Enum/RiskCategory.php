<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Category resulting from the F.08.0 valuation score (PC.03.0 §5.2): trivial (1-2),
 * moderate (3-4) or critical (6-9). Computed by {@see \App\Service\RiskScoreCalculator}; it is
 * never edited directly.
 *
 * The same three bands apply to risks and opportunities — only the displayed wording differs
 * ("Riesgo crítico" vs "Oportunidad crítica"), resolved via {@see label()}.
 */
enum RiskCategory: string
{
    case TRIVIAL = 'trivial';
    case MODERATE = 'moderate';
    case CRITICAL = 'critical';

    /**
     * Human-facing label for this category, worded for the given item type (PC.03.0 §5.2).
     *
     * @param RiskOpportunityType $type whether the item is a risk or an opportunity
     *
     * @return string the display name (e.g. "Riesgo moderado" / "Oportunidad crítica")
     */
    public function label(RiskOpportunityType $type): string
    {
        $noun = RiskOpportunityType::RISK === $type ? 'Riesgo' : 'Oportunidad';
        $feminine = RiskOpportunityType::OPPORTUNITY === $type;

        // "Oportunidad" is feminine in Spanish, so the adjective must agree in gender.
        $adjective = match ($this) {
            self::TRIVIAL => 'trivial',
            self::MODERATE => $feminine ? 'moderada' : 'moderado',
            self::CRITICAL => $feminine ? 'crítica' : 'crítico',
        };

        return $noun.' '.$adjective;
    }

    /**
     * Whether an item in this category requires a documented action plan (PC.03.0 §5.3): the ones of
     * moderate or critical relevance do; trivial ones do not. Single, central definition of the
     * "relevance" threshold so it can be revisited in one place.
     *
     * @return bool true for moderate and critical, false for trivial
     */
    public function requiresActionPlan(): bool
    {
        return self::MODERATE === $this || self::CRITICAL === $this;
    }
}
