<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Enum\ReviewSectionKey;
use App\Repository\IndicatorRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;
use App\Util\DecimalFormatter;

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
        $perIndicator = [];
        $breaches = [];
        foreach ($this->indicators->findAllWithMeasurements() as $indicator) {
            $measuredThisYear = 0;
            $breachedThisYear = 0;
            foreach ($indicator->getMeasurements() as $measurement) {
                if ($measurement->getYear() !== $year) {
                    continue;
                }

                ++$measuredCount;
                ++$measuredThisYear;
                if ($measurement->isBreached()) {
                    ++$breachedThisYear;
                    $breaches[] = sprintf(
                        '- %s (mes %d): valor %s (referencia %s)',
                        $indicator->getName(),
                        $measurement->getMonth(),
                        DecimalFormatter::display($measurement->getValue()),
                        $indicator->getReferenceValue() ?? '—',
                    );
                }
            }

            if ($measuredThisYear > 0) {
                $perIndicator[] = sprintf(
                    '- %s (referencia %s): %d medición(es), %d fuera de referencia.',
                    $indicator->getName(),
                    $indicator->getReferenceValue() ?? '—',
                    $measuredThisYear,
                    $breachedThisYear,
                );
            }
        }

        if (0 === $measuredCount) {
            return '';
        }

        $header = sprintf(
            'Mediciones de indicadores en %d: %d sobre %d indicador(es), de las cuales %d superaron el valor de referencia.',
            $year,
            $measuredCount,
            \count($perIndicator),
            \count($breaches),
        );

        $parts = [$header, '', 'Indicadores medidos:', ...$perIndicator];
        if ([] !== $breaches) {
            $parts[] = '';
            $parts[] = 'Desviaciones:';
            $parts = [...$parts, ...$breaches];
        }

        return implode("\n", $parts);
    }
}
