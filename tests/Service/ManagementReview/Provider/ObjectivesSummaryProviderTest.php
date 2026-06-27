<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\Objective;
use App\Enum\ObjectiveStatus;
use App\Repository\ObjectiveRepository;
use App\Service\ManagementReview\Provider\ObjectivesSummaryProvider;
use PHPUnit\Framework\TestCase;

final class ObjectivesSummaryProviderTest extends TestCase
{
    private function objective(string $reference, string $description, ObjectiveStatus $status): Objective
    {
        return (new Objective())
            ->setReference($reference)
            ->setDescription($description)
            ->setStatus($status);
    }

    public function testListsObjectivesWithStatusBreakdown(): void
    {
        $repo = $this->createMock(ObjectiveRepository::class);
        $repo->method('findForSchoolYear')->with('2025-2026')->willReturn([
            $this->objective('OBJ-01', 'Reducir el consumo de agua 5%', ObjectiveStatus::IN_PROGRESS),
            $this->objective('OBJ-02', 'Reducir el consumo energético 5%', ObjectiveStatus::ACHIEVED),
        ]);

        $summary = (new ObjectivesSummaryProvider($repo))->summarize('2025-2026');

        self::assertStringContainsString('Objetivos ambientales: 2', $summary);
        self::assertStringContainsString('OBJ-01', $summary);
        self::assertStringContainsString('Reducir el consumo energético 5%', $summary);
    }

    public function testReturnsEmptyWhenNoObjectives(): void
    {
        $repo = $this->createMock(ObjectiveRepository::class);
        $repo->method('findForSchoolYear')->willReturn([]);

        self::assertSame('', (new ObjectivesSummaryProvider($repo))->summarize('2025-2026'));
    }
}
