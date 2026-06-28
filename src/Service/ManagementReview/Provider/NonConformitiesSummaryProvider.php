<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Enum\NonConformityStatus;
use App\Enum\ReviewSectionKey;
use App\Repository\NonConformityRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the non-conformities of the course's closing year (F.10.0/F.11.0), for the
 * "non-conformities and corrective actions" section of the management review.
 */
final class NonConformitiesSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly NonConformityRepository $nonConformities,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::NON_CONFORMITIES;
    }

    public function summarize(string $exercise): string
    {
        $year = ExerciseYears::endYear($exercise);

        $counts = [];
        $lines = [];
        foreach ($this->nonConformities->findAllOrdered() as $nc) {
            if ($nc->getYear() !== $year) {
                continue;
            }

            $status = $nc->getStatus();
            $counts[$status->value] = ($counts[$status->value] ?? 0) + 1;
            $lines[] = sprintf(
                '- %s (%s): %s [%s]',
                $nc->getReference(),
                $nc->getOrigin()->label(),
                $nc->getDescription(),
                $status->label(),
            );
        }

        if ([] === $lines) {
            return '';
        }

        $breakdown = [];
        foreach (NonConformityStatus::cases() as $status) {
            if (($counts[$status->value] ?? 0) > 0) {
                $breakdown[] = sprintf('%s: %d', $status->label(), $counts[$status->value]);
            }
        }

        $header = sprintf('No conformidades del año %d: %d (%s).', $year, \count($lines), implode(', ', $breakdown));

        return implode("\n", [$header, '', ...$lines]);
    }
}
