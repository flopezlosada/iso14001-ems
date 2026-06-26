<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Enum\ReviewSectionKey;
use App\Repository\IndicatorRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the indicator measurements of the course's closing year (F.09.0), highlighting the
 * ones that breached their reference value, for the "monitoring and measurement results" section
 * of the management review.
 */
final class IndicatorsSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly IndicatorRepository $indicators,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::MONITORING_RESULTS;
    }

    public function summarize(string $exercise): string
    {
        $year = ExerciseYears::endYear($exercise);

        $measuredCount = 0;
        $breaches = [];
        foreach ($this->indicators->findAllWithMeasurements() as $indicator) {
            foreach ($indicator->getMeasurements() as $measurement) {
                if ($measurement->getYear() !== $year) {
                    continue;
                }

                ++$measuredCount;
                if ($measurement->isBreached()) {
                    $breaches[] = sprintf(
                        '- %s (mes %d): valor %s (referencia %s)',
                        $indicator->getName(),
                        $measurement->getMonth(),
                        $measurement->getValue(),
                        $indicator->getReferenceValue() ?? '—',
                    );
                }
            }
        }

        if (0 === $measuredCount) {
            return '';
        }

        $header = sprintf(
            'Mediciones de indicadores en %d: %d, de las cuales %d superaron el valor de referencia.',
            $year,
            $measuredCount,
            \count($breaches),
        );

        if ([] === $breaches) {
            return $header;
        }

        return implode("\n", [$header, '', 'Desviaciones:', ...$breaches]);
    }
}
