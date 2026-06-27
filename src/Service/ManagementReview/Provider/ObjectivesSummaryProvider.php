<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Enum\ObjectiveStatus;
use App\Enum\ReviewSectionKey;
use App\Repository\ObjectiveRepository;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the environmental objectives and their degree of achievement (PG-06.04), for the
 * "objectives; degree of achievement" section of the management review.
 *
 * Objectives are stored per course, so only the reviewed exercise's objectives are listed with their
 * status (mirroring how the real RG-09.03.01 reports "the objectives for this year… in progress").
 */
final class ObjectivesSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly ObjectiveRepository $objectives,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::OBJECTIVES;
    }

    public function summarize(string $exercise): string
    {
        // The persisted school year is always hyphenated (entity regex), but the review exercise may
        // carry a slash in historical/seed data, so normalise before matching (as the sibling
        // providers do).
        $all = $this->objectives->findForSchoolYear(str_replace('/', '-', $exercise));
        if ([] === $all) {
            return '';
        }

        $counts = [];
        $lines = [];
        foreach ($all as $objective) {
            $status = $objective->getStatus();
            $counts[$status->value] = ($counts[$status->value] ?? 0) + 1;
            $lines[] = sprintf(
                '- %s: %s — %s',
                $objective->getReference(),
                $objective->getDescription(),
                $status->label(),
            );
        }

        $breakdown = [];
        foreach (ObjectiveStatus::cases() as $status) {
            if (($counts[$status->value] ?? 0) > 0) {
                $breakdown[] = sprintf('%s: %d', $status->label(), $counts[$status->value]);
            }
        }

        $header = sprintf('Objetivos ambientales: %d (%s).', \count($all), implode(', ', $breakdown));

        return implode("\n", [$header, '', ...$lines]);
    }
}
