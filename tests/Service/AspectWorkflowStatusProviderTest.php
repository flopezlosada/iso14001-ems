<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AspectEvaluation;
use App\Repository\AspectEvaluationRepository;
use App\Repository\EnvironmentalAspectRepository;
use App\Service\AspectWorkflowStatusProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the environmental-aspects workflow status: the pending counts must be derived
 * correctly from the active-aspect total and the year's evaluations.
 */
final class AspectWorkflowStatusProviderTest extends TestCase
{
    public function testCountsPendingWorkForTheYear(): void
    {
        $significant = (new AspectEvaluation())->setSignificant(true);
        $notSignificant = (new AspectEvaluation())->setSignificant(false);

        $provider = new AspectWorkflowStatusProvider(
            $this->aspectsWithActiveCount(5),
            $this->evaluationsReturning([$significant, $notSignificant]),
        );

        $status = $provider->for(2026);

        self::assertSame(2026, $status->year);
        self::assertSame(5, $status->totalActive);
        self::assertSame(3, $status->unevaluated);   // 5 active - 2 evaluated
        self::assertSame(1, $status->significant);    // only the significant one
        self::assertFalse($status->isComplete());
    }

    public function testIsCompleteWhenEveryActiveAspectIsEvaluated(): void
    {
        $provider = new AspectWorkflowStatusProvider(
            $this->aspectsWithActiveCount(2),
            $this->evaluationsReturning([
                (new AspectEvaluation())->setSignificant(false),
                (new AspectEvaluation())->setSignificant(false),
            ]),
        );

        self::assertTrue($provider->for(2026)->isComplete());
    }

    public function testIsNotCompleteWithoutAnyAspect(): void
    {
        $provider = new AspectWorkflowStatusProvider(
            $this->aspectsWithActiveCount(0),
            $this->evaluationsReturning([]),
        );

        $status = $provider->for(2026);

        self::assertSame(0, $status->unevaluated);
        self::assertFalse($status->isComplete());
    }

    private function aspectsWithActiveCount(int $count): EnvironmentalAspectRepository
    {
        $repo = $this->createStub(EnvironmentalAspectRepository::class);
        $repo->method('countActive')->willReturn($count);

        return $repo;
    }

    /**
     * @param AspectEvaluation[] $evaluations the year's evaluations the repository should return
     */
    private function evaluationsReturning(array $evaluations): AspectEvaluationRepository
    {
        $repo = $this->createStub(AspectEvaluationRepository::class);
        $repo->method('findByYearForActiveAspects')->willReturn($evaluations);

        return $repo;
    }
}
