<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Enum\ReviewSectionKey;
use App\Repository\EnvironmentalAspectRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the environmental aspects evaluated as significant in the course's closing year
 * (RG-06.01.01), for the "changes in significant aspects" section of the management review.
 */
final class SignificantAspectsSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly EnvironmentalAspectRepository $aspects,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::SIGNIFICANT_ASPECTS;
    }

    public function summarize(string $exercise): string
    {
        $year = ExerciseYears::endYear($exercise);
        $lines = [];

        foreach ($this->aspects->findAllWithEvaluations() as $aspect) {
            foreach ($aspect->getEvaluations() as $evaluation) {
                if ($evaluation->getYear() !== $year || !$evaluation->isSignificant()) {
                    continue;
                }

                $category = $aspect->getCategory();
                $lines[] = sprintf(
                    '- %s%s (puntuación %d)',
                    $aspect->getName(),
                    null !== $category ? ' ['.$category->label().']' : '',
                    $evaluation->getSignificanceScore(),
                );
                break;
            }
        }

        if ([] === $lines) {
            return '';
        }

        $header = sprintf('Aspectos ambientales significativos en %d: %d.', $year, \count($lines));

        return implode("\n", [$header, '', ...$lines]);
    }
}
