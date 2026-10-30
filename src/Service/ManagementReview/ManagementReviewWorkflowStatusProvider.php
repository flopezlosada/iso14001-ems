<?php

declare(strict_types=1);

namespace App\Service\ManagementReview;

use App\Enum\ReviewSectionGroup;
use App\Repository\ManagementReviewRepository;
use App\Util\SchoolYear;

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
     * The course whose review the workflow guide should drive on a given date. A course is reviewed
     * once it has run its course (the annual obligation falls due at the start of the next one), so
     * this is normally the previous course: pointing at the course that has just started would invite
     * creating a premature acta for it. The current course is chosen only when its acta already
     * exists, i.e. Direction chose to hold the review before the course ended (e.g. in June).
     *
     * @param \DateTimeImmutable $on the reference date (today)
     *
     * @return string the school year under review, in "YYYY-YYYY" format
     */
    public function exerciseUnderReview(\DateTimeImmutable $on): string
    {
        $current = SchoolYear::current($on);

        return null !== $this->reviews->findOneByExercise($current) ? $current : SchoolYear::previous($current);
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
