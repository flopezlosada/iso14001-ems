<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RiskAction;
use App\Entity\RiskAssessment;
use App\Entity\User;
use App\Enum\RiskCategory;
use App\Repository\DafoAnalysisRepository;
use App\Repository\RiskAssessmentRepository;
use App\Repository\RiskOpportunityRepository;
use App\Service\RiskWorkflowStatusProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the risk-and-opportunity workflow status: the pending counts must be derived
 * correctly from the DAFO existence, the item total and the exercise's valuations.
 */
final class RiskWorkflowStatusProviderTest extends TestCase
{
    public function testCountsPendingWorkForTheExercise(): void
    {
        // A critical valuation, approved, with no action plan → relevant without action.
        $critical = (new RiskAssessment())
            ->setExercise('2025-2026')
            ->setCategory(RiskCategory::CRITICAL)
            ->setApprovedBy(new User());
        // A trivial valuation, not approved → counts as unapproved but never as "relevant without action".
        $trivial = (new RiskAssessment())
            ->setExercise('2025-2026')
            ->setCategory(RiskCategory::TRIVIAL);
        // A moderate valuation, not approved, but with an action plan → unapproved, not "without action".
        $moderate = (new RiskAssessment())
            ->setExercise('2025-2026')
            ->setCategory(RiskCategory::MODERATE);
        $moderate->addAction((new RiskAction())->setDescription('Plan'));

        $provider = new RiskWorkflowStatusProvider(
            $this->risksReturning(5),
            $this->assessmentsReturning('2025-2026', [$critical, $trivial, $moderate]),
            $this->dafoReturning('2025-2026', true),
        );

        $status = $provider->for('2025-2026');

        self::assertSame('2025-2026', $status->exercise);
        self::assertTrue($status->dafoReady);
        self::assertSame(5, $status->totalItems);
        self::assertSame(2, $status->unvalued);                 // 5 items - 3 valued
        self::assertSame(2, $status->unapproved);               // trivial + moderate
        self::assertSame(1, $status->relevantWithoutActions);   // only the critical without a plan
        self::assertFalse($status->isComplete());
    }

    public function testIsCompleteWhenNothingIsPending(): void
    {
        $approved = (new RiskAssessment())
            ->setExercise('2025-2026')
            ->setCategory(RiskCategory::TRIVIAL)
            ->setApprovedBy(new User());

        $provider = new RiskWorkflowStatusProvider(
            $this->risksReturning(1),
            $this->assessmentsReturning('2025-2026', [$approved]),
            $this->dafoReturning('2025-2026', true),
        );

        self::assertTrue($provider->for('2025-2026')->isComplete());
    }

    private function risksReturning(int $count): RiskOpportunityRepository
    {
        $repo = $this->createMock(RiskOpportunityRepository::class);
        $repo->method('count')->with([])->willReturn($count);

        return $repo;
    }

    /**
     * @param RiskAssessment[] $valuations
     */
    private function assessmentsReturning(string $exercise, array $valuations): RiskAssessmentRepository
    {
        $repo = $this->createMock(RiskAssessmentRepository::class);
        $repo->method('findByExerciseWithActions')->with($exercise)->willReturn($valuations);

        return $repo;
    }

    private function dafoReturning(string $exercise, bool $exists): DafoAnalysisRepository
    {
        $repo = $this->createMock(DafoAnalysisRepository::class);
        $repo->method('existsForSchoolYear')->with($exercise)->willReturn($exists);

        return $repo;
    }
}
