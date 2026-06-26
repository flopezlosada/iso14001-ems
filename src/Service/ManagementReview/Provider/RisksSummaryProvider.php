<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Enum\ReviewSectionKey;
use App\Enum\RiskOpportunityType;
use App\Repository\RiskOpportunityRepository;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the risks and opportunities valued for the course (F.08.0), for the
 * "changes in risks and opportunities" section of the management review.
 */
final class RisksSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly RiskOpportunityRepository $risks,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::RISKS_OPPORTUNITIES;
    }

    public function summarize(string $exercise): string
    {
        $riskCount = 0;
        $opportunityCount = 0;
        $lines = [];

        foreach ($this->risks->findAllOrdered() as $item) {
            $assessment = $this->assessmentForExercise($item, $exercise);
            if (null === $assessment || null === $assessment->getCategory()) {
                continue;
            }

            $type = $item->getType();
            if (RiskOpportunityType::RISK === $type) {
                ++$riskCount;
            } else {
                ++$opportunityCount;
            }

            $lines[] = sprintf(
                '- %s: «%s» — %s (puntuación %d)',
                $type->label(),
                $item->getDescription(),
                $assessment->getCategory()->label($type),
                $assessment->getScore(),
            );
        }

        if ([] === $lines) {
            return '';
        }

        $header = sprintf(
            'Riesgos y oportunidades valorados en el curso %s: %d riesgos y %d oportunidades.',
            $exercise,
            $riskCount,
            $opportunityCount,
        );

        return implode("\n", [$header, '', ...$lines]);
    }

    /**
     * The valuation of the given item for the requested course, if any.
     *
     * @param RiskOpportunity $item     the risk/opportunity
     * @param string          $exercise the school year, e.g. "2025-2026"
     *
     * @return RiskAssessment|null the matching valuation, or null
     */
    private function assessmentForExercise(RiskOpportunity $item, string $exercise): ?RiskAssessment
    {
        // Compare normalising the separator: the entity validates the course with a hyphen
        // ("2025-2026"), but historical/seed data may carry a slash ("2025/2026"), so we match on
        // either to avoid silently dropping valuations.
        $target = str_replace('/', '-', $exercise);
        foreach ($item->getAssessments() as $assessment) {
            if (str_replace('/', '-', $assessment->getExercise()) === $target) {
                return $assessment;
            }
        }

        return null;
    }
}
