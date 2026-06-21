<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\CorrectiveAction;
use App\Enum\Efficacy;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of {@see CorrectiveAction}: the derived reference and the review flag.
 */
final class CorrectiveActionTest extends TestCase
{
    public function testReferenceIsDerivedFromSequenceWithTwoDigits(): void
    {
        self::assertSame('AC.01', (new CorrectiveAction())->setSequence(1)->getReference());
        self::assertSame('AC.10', (new CorrectiveAction())->setSequence(10)->getReference());
    }

    public function testIsReviewedOnlyWhenEfficacyIsSet(): void
    {
        $action = new CorrectiveAction();
        self::assertFalse($action->isReviewed());

        $action->setEfficacy(Efficacy::NOT_OK);
        self::assertTrue($action->isReviewed());
    }

    public function testEfficacyLabels(): void
    {
        self::assertSame('OK', Efficacy::OK->label());
        self::assertSame('No OK', Efficacy::NOT_OK->label());
    }
}
