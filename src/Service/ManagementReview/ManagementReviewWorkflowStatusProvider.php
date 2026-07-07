<?php

declare(strict_types=1);

namespace App\Service\ManagementReview;

use App\Enum\ReviewSectionGroup;
use App\Repository\ManagementReviewRepository;

/**
 * Computes the {@see ManagementReviewWorkflowStatus} of a course from the existing data, without an
 * N+1: the course's review is loaded once with its sections and participants, and every pending
 * signal is derived from that single graph.
 */
final readonly class ManagementReviewWorkflowStatusProvider
{
    public function __construct(
        private ManagementReviewRepository $reviews,
    ) {
    }

    /**
     * Builds the management-review workflow status for the given course.
     *
     * @param string $exercise the school year, in "YYYY-YYYY" format
     *
     * @return ManagementReviewWorkflowStatus the pending-work snapshot for that course
     */
    public function for(string $exercise): ManagementReviewWorkflowStatus
    {
        $review = $this->reviews->findByExerciseWithSections($exercise);
        if (null === $review) {
            return new ManagementReviewWorkflowStatus($exercise, null, false, 0, 0, false, false, false);
        }

        $inputsPending = 0;
        $decisionsPending = 0;
        foreach ($review->getSections() as $section) {
            if (ReviewSectionGroup::OUTPUT === $section->getSectionKey()->group()) {
                // An output section is a decision: pending until Direction picks its verdict.
                if (null === $section->getDecision()) {
                    ++$decisionsPending;
                }
                continue;
            }
            // An input section is pending until it has text (auto sections are seeded; the rest are
            // written by hand — "sin cambios" is a valid, and required, thing to state).
            $content = $section->getContent();
            if (null === $content || '' === trim($content)) {
                ++$inputsPending;
            }
        }

        return new ManagementReviewWorkflowStatus(
            $exercise,
            $review->getId(),
            true,
            $inputsPending,
            $decisionsPending,
            null !== $review->getMeetingDate() && !$review->getParticipants()->isEmpty(),
            $review->isApproved(),
            $review->isDigitallySigned(),
        );
    }
}
