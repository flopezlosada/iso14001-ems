<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whether an F.08.0 item is a risk or an opportunity (PC.03.0 §5.2).
 *
 * The distinction also drives the label of the first scoring factor: for risks it is
 * "Probabilidad" (likelihood of occurrence), for opportunities it is "Potencialidad" (the
 * organisation's capacity to carry it out). The numeric factor (1-3) and the formula are the same.
 */
enum RiskOpportunityType: string
{
    case RISK = 'risk';
    case OPPORTUNITY = 'opportunity';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::RISK => 'Riesgo',
            self::OPPORTUNITY => 'Oportunidad',
        };
    }

    /**
     * Label of the first scoring factor for this type: "Probabilidad" for risks,
     * "Potencialidad" for opportunities (PC.03.0 §5.2).
     *
     * @return string the factor label
     */
    public function firstFactorLabel(): string
    {
        return match ($this) {
            self::RISK => 'Probabilidad',
            self::OPPORTUNITY => 'Potencialidad',
        };
    }
}
