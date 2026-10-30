<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview;

use App\Entity\ManagementReview;
use App\Entity\ManagementReviewSection;
use App\Entity\User;
use App\Enum\ReviewSectionKey;
use App\Repository\ManagementReviewRepository;
use App\Service\ManagementReview\ManagementReviewWorkflowStatusProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the management-review workflow status: the pending signals must be derived from the
 * course's review, its sections (inputs without content, decisions not made), meeting metadata and
 * approval/signature.
 */
final class ManagementReviewWorkflowStatusProviderTest extends TestCase
{
    public function testReportsNothingWhenTheCourseHasNoReview(): void
    {
        $provider = new ManagementReviewWorkflowStatusProvider($this->reviewsReturning('2025-2026', null));

        $status = $provider->for('2025-2026');

        self::assertSame('2025-2026', $status->exercise);
        self::assertNull($status->reviewId);
        self::assertFalse($status->exists);
        self::assertSame(0, $status->inputsPending);
        self::assertSame(0, $status->decisionsPending);
        self::assertFalse($status->meetingRecorded);
        self::assertFalse($status->isComplete());
    }

    public function testCountsPendingInputsDecisionsAndMissingMeeting(): void
    {
        $review = (new ManagementReview())->setExercise('2025-2026');
        // One input filled, one input empty -> one input pending.
        $review->addSection($this->section(ReviewSectionKey::PREVIOUS_ACTIONS, content: 'Cerradas.'));
        $review->addSection($this->section(ReviewSectionKey::CONTEXT_CHANGES, content: null));
        // One decision made, one not -> one decision pending.
        $review->addSection($this->section(ReviewSectionKey::CONCLUSIONS, decision: 'Adecuado con mejoras'));
        $review->addSection($this->section(ReviewSectionKey::SYSTEM_CHANGES, decision: null));

        $provider = new ManagementReviewWorkflowStatusProvider($this->reviewsReturning('2025-2026', $review));

        $status = $provider->for('2025-2026');

        self::assertTrue($status->exists);
        self::assertSame(1, $status->inputsPending);
        self::assertSame(1, $status->decisionsPending);
        self::assertFalse($status->meetingRecorded);   // no meeting date nor participants
        self::assertFalse($status->approved);
        self::assertFalse($status->isComplete());
    }

    public function testIsCompleteWhenEverythingIsFilledMeetingHeldAndApproved(): void
    {
        $review = (new ManagementReview())->setExercise('2025-2026');
        $review->addSection($this->section(ReviewSectionKey::PREVIOUS_ACTIONS, content: 'Cerradas.'));
        $review->addSection($this->section(ReviewSectionKey::CONCLUSIONS, decision: 'Adecuado con mejoras'));
        $review->setMeetingDate(new \DateTimeImmutable('2026-07-01'));
        $review->addParticipant(new User());
        $review->setApprovedAt(new \DateTimeImmutable('2026-07-02'));

        $provider = new ManagementReviewWorkflowStatusProvider($this->reviewsReturning('2025-2026', $review));

        $status = $provider->for('2025-2026');

        self::assertSame(0, $status->inputsPending);
        self::assertSame(0, $status->decisionsPending);
        self::assertTrue($status->meetingRecorded);
        self::assertTrue($status->approved);
        self::assertFalse($status->signed);             // signature is optional
        self::assertTrue($status->isComplete());
    }

    public function testReviewsThePreviousCourseOnceANewOneHasStarted(): void
    {
        $repo = $this->createMock(ManagementReviewRepository::class);
        $repo->method('findOneByExercise')->with('2026-2027')->willReturn(null);

        $provider = new ManagementReviewWorkflowStatusProvider($repo);

        // 30/10/2026 is in course 2026-2027, which has no acta: the course under review is 2025-2026.
        self::assertSame('2025-2026', $provider->exerciseUnderReview(new \DateTimeImmutable('2026-10-30')));
    }

    public function testReviewsTheCurrentCourseWhenItsActaWasStartedBeforeItEnded(): void
    {
        $repo = $this->createMock(ManagementReviewRepository::class);
        $repo->method('findOneByExercise')->with('2025-2026')->willReturn((new ManagementReview())->setExercise('2025-2026'));

        $provider = new ManagementReviewWorkflowStatusProvider($repo);

        // June 2026 is still course 2025-2026 and its acta already exists: the guide follows it.
        self::assertSame('2025-2026', $provider->exerciseUnderReview(new \DateTimeImmutable('2026-06-20')));
    }

    private function section(ReviewSectionKey $key, ?string $content = null, ?string $decision = null): ManagementReviewSection
    {
        return (new ManagementReviewSection())
            ->setSectionKey($key)
            ->setContent($content)
            ->setDecision($decision);
    }

    private function reviewsReturning(string $exercise, ?ManagementReview $review): ManagementReviewRepository
    {
        $repo = $this->createMock(ManagementReviewRepository::class);
        $repo->method('findByExerciseWithSections')->with($exercise)->willReturn($review);

        return $repo;
    }
}
