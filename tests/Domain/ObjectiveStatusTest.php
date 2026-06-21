<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Objective;
use App\Enum\ObjectiveStatus;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants for objectives: status labels and the not-achieved helper that drives
 * escalation to a non-conformity.
 */
final class ObjectiveStatusTest extends TestCase
{
    public function testStatusLabels(): void
    {
        self::assertSame('En curso', ObjectiveStatus::IN_PROGRESS->label());
        self::assertSame('Cumplido', ObjectiveStatus::ACHIEVED->label());
        self::assertSame('No cumplido', ObjectiveStatus::NOT_ACHIEVED->label());
        self::assertSame('No aplica', ObjectiveStatus::NOT_APPLICABLE->label());
    }

    public function testIsNotAchievedReflectsTheStatus(): void
    {
        $objective = new Objective();
        self::assertFalse($objective->isNotAchieved());

        $objective->setStatus(ObjectiveStatus::NOT_ACHIEVED);
        self::assertTrue($objective->isNotAchieved());

        $objective->setStatus(ObjectiveStatus::ACHIEVED);
        self::assertFalse($objective->isNotAchieved());
    }
}
