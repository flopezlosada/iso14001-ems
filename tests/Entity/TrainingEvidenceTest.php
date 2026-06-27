<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\TrainingAction;
use App\Entity\TrainingEvidence;
use App\Enum\TrainingType;
use PHPUnit\Framework\TestCase;

final class TrainingEvidenceTest extends TestCase
{
    public function testQuestionnaireDefaultsToNotCompleted(): void
    {
        $evidence = new TrainingEvidence();
        self::assertFalse($evidence->isQuestionnaireCompleted());

        $evidence->setQuestionnaireCompleted(true);
        self::assertTrue($evidence->isQuestionnaireCompleted());
    }

    public function testTouchUpdatesTheUpdatedAtTimestamp(): void
    {
        $evidence = new TrainingEvidence();
        $createdAt = $evidence->getCreatedAt();
        // Constructed equal; touch() (a PrePersist/PreUpdate callback) advances updatedAt.
        self::assertEquals($createdAt, $evidence->getUpdatedAt());

        $evidence->touch();

        self::assertGreaterThanOrEqual($createdAt, $evidence->getUpdatedAt());
        // created_at is never moved by touch().
        self::assertSame($createdAt, $evidence->getCreatedAt());
    }

    public function testTrainingActionIsOptionalAndSettable(): void
    {
        $evidence = new TrainingEvidence();
        self::assertNull($evidence->getTrainingAction());

        $action = (new TrainingAction())->setPlanYear(2025)->setType(TrainingType::INTERNAL);
        $evidence->setTrainingAction($action);
        self::assertSame($action, $evidence->getTrainingAction());

        // The link can be severed (mirrors the ON DELETE SET NULL on the column).
        $evidence->setTrainingAction(null);
        self::assertNull($evidence->getTrainingAction());
    }

    public function testStoresAllRegisterColumns(): void
    {
        $evidence = (new TrainingEvidence())
            ->setPersonName('Persona de ejemplo')
            ->setTrainingDescription('Sensibilización ambiental ISO 14001')
            ->setTrainingDate(new \DateTimeImmutable('2025-09-03'))
            ->setQuestionnaireCompleted(true);

        self::assertSame('Persona de ejemplo', $evidence->getPersonName());
        self::assertSame('Sensibilización ambiental ISO 14001', $evidence->getTrainingDescription());
        self::assertEquals(new \DateTimeImmutable('2025-09-03'), $evidence->getTrainingDate());
        self::assertTrue($evidence->isQuestionnaireCompleted());
    }
}
