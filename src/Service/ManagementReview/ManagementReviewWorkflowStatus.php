<?php

declare(strict_types=1);

namespace App\Service\ManagementReview;

/**
 * The state of the management-review workflow (PG-09.03.00 / RG-09.03.01, ISO 14001:2015 §9.3) for
 * one course: what is done and what is still pending, so the module can guide the work (create the
 * record → complete the §9.3.2 inputs → record the §9.3.3 decisions → hold the meeting → approve →
 * sign) instead of showing a bare list of reviews.
 *
 * It is a snapshot of pending signals derived from real data (not a linear wizard). Built by
 * {@see ManagementReviewWorkflowStatusProvider}.
 */
final readonly class ManagementReviewWorkflowStatus
{
    /**
     * @param string   $exercise         the school year this snapshot describes ("YYYY-YYYY")
     * @param int|null $reviewId         the id of the course's review (to link to it), or null if none
     * @param bool     $exists           whether the review record for the course has been created
     * @param int      $inputsPending    §9.3.2 input sections still without content
     * @param int      $decisionsPending §9.3.3 output sections still without a decision
     * @param bool     $meetingRecorded  whether the meeting date and its participants are recorded
     * @param bool     $approved         whether Direction has approved (signed off) the review
     * @param bool     $signed           whether a level-1a signed PDF is attached (optional extra)
     */
    public function __construct(
        public string $exercise,
        public ?int $reviewId,
        public bool $exists,
        public int $inputsPending,
        public int $decisionsPending,
        public bool $meetingRecorded,
        public bool $approved,
        public bool $signed,
    ) {
    }

    /**
     * Whether the course's review is fully done: it exists, every input and decision is filled in,
     * the meeting is recorded and Direction has approved it. The level-1a signature is an optional
     * extra and does not gate completeness.
     *
     * @return bool true when no required signal is pending
     */
    public function isComplete(): bool
    {
        return $this->exists
            && 0 === $this->inputsPending
            && 0 === $this->decisionsPending
            && $this->meetingRecorded
            && $this->approved;
    }
}
