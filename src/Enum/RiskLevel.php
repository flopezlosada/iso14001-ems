<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A single 1-3 scoring level used for both factors of the F.08.0 valuation
 * (probability/potentiality and impact), per PC.03.0 §5.2. The backing value IS the factor used
 * in the product probability × impact.
 */
enum RiskLevel: int
{
    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Bajo',
            self::MEDIUM => 'Medio',
            self::HIGH => 'Alto',
        };
    }
}
