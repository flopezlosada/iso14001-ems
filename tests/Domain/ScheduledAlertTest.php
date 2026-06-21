<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Document;
use App\Entity\ScheduledAlert;
use App\Enum\AlertFrequency;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of the alert engine: due detection, escalation window and cycle rolling.
 */
final class ScheduledAlertTest extends TestCase
{
    private function makeAlert(AlertFrequency $frequency, string $nextDue, ?int $escalationDays = null): ScheduledAlert
    {
        $alert = new ScheduledAlert();
        $alert->setDocument(new Document());
        $alert->setFrequency($frequency);
        $alert->setNextDueDate(new \DateTimeImmutable($nextDue));
        $alert->setEscalationDays($escalationDays);

        return $alert;
    }

    public function testIsDueOnAndAfterDueDate(): void
    {
        $alert = $this->makeAlert(AlertFrequency::ANNUAL, '2026-06-01');

        self::assertFalse($alert->isDue(new \DateTimeImmutable('2026-05-31')));
        self::assertTrue($alert->isDue(new \DateTimeImmutable('2026-06-01')));
        self::assertTrue($alert->isDue(new \DateTimeImmutable('2026-06-02')));
    }

    public function testEscalationDisabledWhenNoEscalationDays(): void
    {
        $alert = $this->makeAlert(AlertFrequency::MONTHLY, '2026-06-01', null);

        self::assertFalse($alert->shouldEscalate(new \DateTimeImmutable('2027-01-01')));
    }

    public function testEscalatesOnlyAfterWindowElapses(): void
    {
        $alert = $this->makeAlert(AlertFrequency::ANNUAL, '2026-06-01', 7);

        self::assertFalse($alert->shouldEscalate(new \DateTimeImmutable('2026-06-07')));
        self::assertTrue($alert->shouldEscalate(new \DateTimeImmutable('2026-06-08')));
    }

    public function testRollAdvancesByFrequencyInterval(): void
    {
        $alert = $this->makeAlert(AlertFrequency::BIANNUAL, '2026-01-01');
        $alert->rollToNextCycle();

        self::assertSame('2026-07-01', $alert->getNextDueDate()->format('Y-m-d'));
    }

    public function testRollIsNoOpForEventDrivenAlerts(): void
    {
        $alert = $this->makeAlert(AlertFrequency::ON_EVENT, '2026-01-01');
        $alert->rollToNextCycle();

        self::assertSame('2026-01-01', $alert->getNextDueDate()->format('Y-m-d'));
    }

    public function testNeedsNotificationWhenDueAndNeverNotified(): void
    {
        $alert = $this->makeAlert(AlertFrequency::MONTHLY, '2026-06-01');

        self::assertFalse($alert->needsNotification(new \DateTimeImmutable('2026-05-31')));
        self::assertTrue($alert->needsNotification(new \DateTimeImmutable('2026-06-01')));
    }

    public function testDoesNotNotifyTwiceInTheSameCycle(): void
    {
        $alert = $this->makeAlert(AlertFrequency::MONTHLY, '2026-06-01');
        // Already notified after the due date → no second reminder this cycle.
        $alert->setLastNotifiedAt(new \DateTimeImmutable('2026-06-02 09:00:00'));

        self::assertFalse($alert->needsNotification(new \DateTimeImmutable('2026-06-03')));
    }

    public function testNotifiesAgainWhenLastNotificationPredatesNewCycle(): void
    {
        $alert = $this->makeAlert(AlertFrequency::MONTHLY, '2026-06-01');
        // Last notified during the previous cycle (before the current due date) → owed again.
        $alert->setLastNotifiedAt(new \DateTimeImmutable('2026-05-02 09:00:00'));

        self::assertTrue($alert->needsNotification(new \DateTimeImmutable('2026-06-01')));
    }

    public function testEventDrivenAlertsNeverNeedScheduledNotification(): void
    {
        $alert = $this->makeAlert(AlertFrequency::ON_EVENT, '2020-01-01');

        self::assertFalse($alert->needsNotification(new \DateTimeImmutable('2026-06-21')));
    }
}
