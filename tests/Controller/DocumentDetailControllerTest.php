<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ApprovalEvent;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\ObligationStatus;
use App\Enum\VersionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the read-only document detail (version history + approval trail), the
 * auditable face of clause 7.5.
 */
final class DocumentDetailControllerTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = (new Document())->setTitle('Documento')->setType(DocumentType::PROCEDURE)->setStatus(ObligationStatus::DONE);
        $em->persist($document);
        $em->flush();

        $client->request('GET', '/documentos/'.$document->getId());

        self::assertResponseRedirects('/login');
    }

    public function testShowsVersionHistoryAndApprovalTrail(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $approver = (new User())->setFullName('Marta Aprobadora')->setEmail('aprobadora@example.test')->setActive(true);
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

        $approval = (new ApprovalEvent())->setApprover($approver)->setIntegrityHash('abcdef0123456789');
        $version->addApprovalEvent($approval);

        $em->persist($approver);
        $em->persist($document);
        $em->persist($version);
        $em->flush();

        $reader = (new User())->setFullName('Lectora')->setEmail('lectora@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        $client->request('GET', '/documentos/'.$document->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'PC.01.0');
        self::assertSelectorTextContains('.detail-grid', 'Procedimiento');
        // The approval trail names who approved the in-force revision.
        self::assertSelectorTextContains('body', 'Marta Aprobadora');
        self::assertSelectorTextContains('.is-in-force', 'En vigor');
    }

    public function testDocumentWithoutVersionsShowsEmptyState(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = (new Document())->setCode('F.99.0')->setTitle('Formato sin emitir')->setType(DocumentType::FORM)->setStatus(ObligationStatus::PENDING);
        $em->persist($document);
        $reader = (new User())->setFullName('Lectora')->setEmail('lectora2@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        $client->request('GET', '/documentos/'.$document->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state', 'Aún no hay versiones');
    }

    public function testUnknownDocumentIs404(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $reader = (new User())->setFullName('Lectora')->setEmail('lectora3@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        $client->request('GET', '/documentos/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
