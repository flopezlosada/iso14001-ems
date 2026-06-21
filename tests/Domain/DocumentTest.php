<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Enum\DocumentType;
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
}
