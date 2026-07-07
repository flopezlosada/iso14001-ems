<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\TrainingAction;
use App\Enum\TrainingActionStatus;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of the training action progress state: {@see TrainingActionStatus} labels and
 * badge classes, and the derivation from the row's own fields in {@see TrainingAction::status()}.
 */
final class TrainingActionStatusTest extends TestCase
{
    public function testEnumLabelsAndBadgeClasses(): void
    {
        self::assertSame('Revisar', TrainingActionStatus::NEEDS_REVIEW->label());
        self::assertSame('Planificada', TrainingActionStatus::PLANNED->label());
        self::assertSame('Pendiente de evaluar', TrainingActionStatus::EXECUTED->label());
        self::assertSame('Evaluada', TrainingActionStatus::EVALUATED->label());

        self::assertSame('badge--review', TrainingActionStatus::NEEDS_REVIEW->badgeClass());
        self::assertSame('badge--draft', TrainingActionStatus::PLANNED->badgeClass());
        self::assertSame('badge--warning', TrainingActionStatus::EXECUTED->badgeClass());
        self::assertSame('badge--success', TrainingActionStatus::EVALUATED->badgeClass());
    }

    public function testPlannedWhenOnlyPlannedDateIsSet(): void
    {
        // A realistic not-yet-delivered action: planned date set, actual date and efficacy still null.
        $action = (new TrainingAction())->setPlannedDate(new \DateTimeImmutable('2026-10-30'));

        self::assertSame(TrainingActionStatus::PLANNED, $action->status());
    }

    public function testExecutedWhenDeliveredButNotYetEvaluated(): void
    {
        $action = (new TrainingAction())
            ->setPlannedDate(new \DateTimeImmutable('2026-10-30'))
            ->setActualDate(new \DateTimeImmutable('2026-11-05'));

        self::assertSame(TrainingActionStatus::EXECUTED, $action->status());
    }

    public function testEvaluatedWhenEfficacyIsFilled(): void
    {
        $action = (new TrainingAction())
            ->setActualDate(new \DateTimeImmutable('2026-11-05'))
            ->setEfficacyEvaluation('Mejoró la segregación de residuos en los talleres.');

        self::assertSame(TrainingActionStatus::EVALUATED, $action->status());
    }

    public function testBlankEfficacyDoesNotCountAsEvaluated(): void
    {
        // A whitespace-only efficacy text must not be mistaken for a real evaluation.
        $action = (new TrainingAction())
            ->setActualDate(new \DateTimeImmutable('2026-11-05'))
            ->setEfficacyEvaluation('   ');

        self::assertSame(TrainingActionStatus::EXECUTED, $action->status());
    }

    public function testNeedsReviewTakesPrecedenceOverTheLifecycle(): void
    {
        // Even a fully evaluated row waits for the human to clear the review flag first.
        $action = (new TrainingAction())
            ->setActualDate(new \DateTimeImmutable('2026-11-05'))
            ->setEfficacyEvaluation('Eficaz.')
            ->setNeedsReview(true);

        self::assertSame(TrainingActionStatus::NEEDS_REVIEW, $action->status());
    }
}
