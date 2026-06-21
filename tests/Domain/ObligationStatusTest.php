<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\ObligationStatus;
use PHPUnit\Framework\TestCase;

/**
 * The manual review status of an obligation: only "done" and "not applicable" are settled; pending
 * and in-review still need action this cycle.
 */
final class ObligationStatusTest extends TestCase
{
    public function testDoneAndNotApplicableAreSettled(): void
    {
        self::assertTrue(ObligationStatus::DONE->isSettled());
        self::assertTrue(ObligationStatus::NOT_APPLICABLE->isSettled());
    }

    public function testPendingAndInReviewAreNotSettled(): void
    {
        self::assertFalse(ObligationStatus::PENDING->isSettled());
        self::assertFalse(ObligationStatus::IN_REVIEW->isSettled());
    }

    public function testEveryStatusHasANonEmptyLabel(): void
    {
        foreach (ObligationStatus::cases() as $status) {
            self::assertNotSame('', $status->label());
        }
    }
}
