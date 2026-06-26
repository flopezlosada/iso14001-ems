<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Enum\ComplianceStatus;
use App\Enum\ReviewSectionKey;
use App\Repository\LegalRequirementRepository;
use App\Service\ManagementReview\ExerciseYears;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the legal requirements register (PC-06.03): the overall compliance breakdown, the
 * non-compliant items and those whose review falls due in the course's closing year, for the
 * "compliance with legal requirements" section of the management review.
 *
 * Legal requirements are a stable catalogue (no per-course field), so the whole register is
 * assessed; the closing year is only used to flag requirements due for review.
 */
final class LegalComplianceSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly LegalRequirementRepository $requirements,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::LEGAL_COMPLIANCE;
    }

    public function summarize(string $exercise): string
    {
        $all = $this->requirements->findAllOrdered();
        if ([] === $all) {
            return '';
        }

        $year = ExerciseYears::endYear($exercise);
        $counts = [];
        $nonCompliant = [];
        $dueForReview = [];

        foreach ($all as $requirement) {
            $status = $requirement->getComplianceStatus();
            $counts[$status->value] = ($counts[$status->value] ?? 0) + 1;

            if ($requirement->isNonCompliant()) {
                $nonCompliant[] = sprintf('- %s: %s', $requirement->getLegalProvision(), $requirement->getSpecificRequirement());
            }

            $nextReview = $requirement->getNextReviewOn();
            if (null !== $nextReview && (int) $nextReview->format('Y') === $year) {
                $dueForReview[] = sprintf('- %s (revisión prevista %s)', $requirement->getLegalProvision(), $nextReview->format('d/m/Y'));
            }
        }

        $breakdown = [];
        foreach (ComplianceStatus::cases() as $status) {
            if (($counts[$status->value] ?? 0) > 0) {
                $breakdown[] = sprintf('%s: %d', $status->label(), $counts[$status->value]);
            }
        }

        $blocks = [sprintf('Requisitos legales: %d (%s).', \count($all), implode(', ', $breakdown))];

        if ([] !== $nonCompliant) {
            $blocks[] = '';
            $blocks[] = 'Requisitos no conformes:';
            $blocks = [...$blocks, ...$nonCompliant];
        }

        if ([] !== $dueForReview) {
            $blocks[] = '';
            $blocks[] = sprintf('Con revisión prevista en %d:', $year);
            $blocks = [...$blocks, ...$dueForReview];
        }

        return implode("\n", $blocks);
    }
}
