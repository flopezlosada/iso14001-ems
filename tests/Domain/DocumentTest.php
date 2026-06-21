<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\ScheduledAlert;
use App\Enum\AlertFrequency;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use App\Enum\ObligationUrgency;
use App\Enum\PdcaPhase;
use App\Enum\VersionStatus;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of {@see Document}: the in-force version selection and the
 * generated-vs-external classification.
 */
final class DocumentTest extends TestCase
{
    private function makeVersion(int $revision, VersionStatus $status): DocumentVersion
    {
        $version = new DocumentVersion();
        $version->setRevisionNumber($revision);
        $version->setStatus($status);

        return $version;
    }

    public function testCurrentVersionIsNullWhenNoneApproved(): void
    {
        $document = new Document();
        $document->addVersion($this->makeVersion(0, VersionStatus::DRAFT));

        self::assertNull($document->getCurrentVersion());
    }

    public function testCurrentVersionIsHighestApprovedRevision(): void
    {
        $document = new Document();
        $document->addVersion($this->makeVersion(0, VersionStatus::OBSOLETE));
        $document->addVersion($this->makeVersion(1, VersionStatus::APPROVED));
        $document->addVersion($this->makeVersion(2, VersionStatus::DRAFT));

        $current = $document->getCurrentVersion();

        self::assertNotNull($current);
        self::assertSame(1, $current->getRevisionNumber());
    }

    public function testFormsAreSystemGeneratedButExternalEvidenceIsNot(): void
    {
        self::assertTrue(DocumentType::FORM->isSystemGenerated());
        self::assertTrue(DocumentType::RECORD->isSystemGenerated());
        self::assertFalse(DocumentType::EXTERNAL_EVIDENCE->isSystemGenerated());
    }

    public function testPhaseIsDerivedFromTheIsoChapter(): void
    {
        $document = new Document();
        $document->setIsoChapter(IsoChapter::IMPROVEMENT);

        self::assertSame(PdcaPhase::ACT, $document->getPhase());
    }

    public function testPhaseIsNullForNonObligationDocuments(): void
    {
        $document = new Document();

        self::assertNull($document->getIsoChapter());
        self::assertNull($document->getPhase());
    }

    public function testStatusDefaultsToPending(): void
    {
        self::assertSame(ObligationStatus::PENDING, (new Document())->getStatus());
    }

    private function makeAlert(AlertFrequency $frequency, string $nextDue): ScheduledAlert
    {
        return (new ScheduledAlert())
            ->setFrequency($frequency)
            ->setNextDueDate(new \DateTimeImmutable($nextDue));
    }

    public function testObligationWithNoAlertsIsOnTrack(): void
    {
        self::assertSame(ObligationUrgency::ON_TRACK, (new Document())->dueStatus(new \DateTimeImmutable('2026-06-21')));
    }

    public function testPastDueDateIsOverdue(): void
    {
        $document = new Document();
        $document->addAlert($this->makeAlert(AlertFrequency::MONTHLY, '2026-05-31'));

        self::assertSame(ObligationUrgency::OVERDUE, $document->dueStatus(new \DateTimeImmutable('2026-06-21')));
    }

    public function testDueWithinWindowIsDueSoon(): void
    {
        $document = new Document();
        $document->addAlert($this->makeAlert(AlertFrequency::MONTHLY, '2026-06-30'));

        self::assertSame(ObligationUrgency::DUE_SOON, $document->dueStatus(new \DateTimeImmutable('2026-06-21'), 30));
    }

    public function testDueBeyondWindowIsOnTrack(): void
    {
        $document = new Document();
        $document->addAlert($this->makeAlert(AlertFrequency::ANNUAL, '2026-12-01'));

        self::assertSame(ObligationUrgency::ON_TRACK, $document->dueStatus(new \DateTimeImmutable('2026-06-21'), 30));
    }

    public function testEventDrivenAlertNeverCountsAsOverdue(): void
    {
        $document = new Document();
        $document->addAlert($this->makeAlert(AlertFrequency::ON_EVENT, '2020-01-01'));

        self::assertSame(ObligationUrgency::EVENT_DRIVEN, $document->dueStatus(new \DateTimeImmutable('2026-06-21')));
    }

    public function testMostUrgentCadenceWinsOnDoubleCadence(): void
    {
        // F.11.0-style: an annual cadence comfortably ahead plus an overdue monthly review.
        $document = new Document();
        $document->addAlert($this->makeAlert(AlertFrequency::ANNUAL, '2026-12-01'));
        $document->addAlert($this->makeAlert(AlertFrequency::MONTHLY, '2026-05-31'));

        self::assertSame(ObligationUrgency::OVERDUE, $document->dueStatus(new \DateTimeImmutable('2026-06-21')));
    }
}
