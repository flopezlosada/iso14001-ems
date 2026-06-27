<?php

declare(strict_types=1);

namespace App\Tests\Service\Document;

use App\Entity\ApprovalEvent;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\ObligationStatus;
use App\Enum\VersionStatus;
use App\Service\Document\DocumentPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the official control sheet (PC.01.0) renders to a real PDF through the full stack
 * (Twig template + dompdf), with Spanish content that exercises diacritics.
 */
final class DocumentPdfGeneratorTest extends KernelTestCase
{
    public function testRendersControlSheetAsPdf(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(DocumentPdfGenerator::class);
        self::assertInstanceOf(DocumentPdfGenerator::class, $generator);

        $document = (new Document())
            ->setCode('PC.01.0')
            ->setTitle('Gestión de la Información Documentada')
            ->setType(DocumentType::PROCEDURE)
            ->setStatus(ObligationStatus::DONE);
        $version = (new DocumentVersion())
            ->setRevisionNumber(0)
            ->setStatus(VersionStatus::APPROVED)
            ->setAuthor('Carlos Autor')
            ->setChangeSummary('Edición inicial.');
        $document->addVersion($version);
        $version->addApprovalEvent(
            (new ApprovalEvent())
                ->setApprover((new User())->setFullName('Marta Directora')->setEmail('marta@example.test')->setActive(true))
                ->setIntegrityHash('seed'),
        );

        $pdf = $generator->render($document, $version);

        // A well-formed PDF starts with the magic bytes and is not a near-empty stub.
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(800, \strlen($pdf));
    }
}
