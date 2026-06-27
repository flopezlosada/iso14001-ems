<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ApprovalEvent;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\ObligationStatus;
use App\Enum\VersionStatus;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;

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

    public function testRegisterListsAllDocumentsIncludingChapterless(): void
    {
        $client = static::createClient();
        $em = $this->em();
        // A manual has no ISO chapter, so it never appears in the obligations cockpit; the register must.
        $manual = (new Document())->setCode('MA-04.01.01')->setTitle('Manual de Gestión Ambiental')->setType(DocumentType::MANUAL)->setStatus(ObligationStatus::DONE);
        $form = (new Document())->setCode('F.04.0')->setTitle('Formato de ejemplo')->setType(DocumentType::FORM)->setStatus(ObligationStatus::PENDING);
        // A document with several versions must appear ONCE despite the versions JOIN.
        $multi = (new Document())->setCode('PC.02.0')->setTitle('Procedimiento con historial')->setType(DocumentType::PROCEDURE)->setStatus(ObligationStatus::DONE);
        $multi->addVersion((new DocumentVersion())->setRevisionNumber(0)->setStatus(VersionStatus::OBSOLETE));
        $multi->addVersion((new DocumentVersion())->setRevisionNumber(1)->setStatus(VersionStatus::APPROVED));
        $em->persist($manual);
        $em->persist($form);
        $em->persist($multi);
        $reader = (new User())->setFullName('Lectora')->setEmail('lectora4@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        $crawler = $client->request('GET', '/documentos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Manual de Gestión Ambiental');
        self::assertSelectorTextContains('body', 'Formato de ejemplo');
        // Three documents, three rows: the multi-version one is not duplicated by the JOIN.
        self::assertCount(3, $crawler->filter('tbody tr'));
    }

    private function persistDocument(EntityManagerInterface $em, string $code): Document
    {
        $document = (new Document())->setCode($code)->setTitle('Documento '.$code)->setType(DocumentType::PROCEDURE)->setStatus(ObligationStatus::DONE);
        $em->persist($document);

        return $document;
    }

    private function loginAdmin(object $client, EntityManagerInterface $em, string $email): void
    {
        $role = (new Role())->setCode('admin')->setName('Administrador')->setAdmin(true);
        $em->persist($role);
        $admin = (new User())->setFullName('Admin')->setEmail($email)->setActive(true)->addAssignedRole($role);
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);
    }

    public function testRegisterExportRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/documentos/export.csv');

        self::assertResponseRedirects('/login');
    }

    public function testRegisterExportsCsvWithHeaderAndRows(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = (new Document())
            ->setCode('PC.01.0')
            ->setTitle('Gestión de la Información Documentada')
            ->setType(DocumentType::PROCEDURE)
            ->setStatus(ObligationStatus::DONE);
        $document->addVersion((new DocumentVersion())->setRevisionNumber(0)->setStatus(VersionStatus::APPROVED));
        $em->persist($document);
        $reader = (new User())->setFullName('Lectora')->setEmail('lectora-export@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        $content = $this->captureCsv($client, '/documentos/export.csv');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('registro-documental-F01.csv', (string) $client->getResponse()->headers->get('Content-Disposition'));
        // Header row (semicolon-separated) and the document's own row, with its in-force revision.
        self::assertStringContainsString('Código;Documento;Tipo;Área;Responsable', $content);
        self::assertStringContainsString('PC.01.0;Gestión de la Información Documentada;Procedimiento', $content);
        self::assertStringContainsString('Rev. 0', $content);
        // An in-force document reads as "Activo" in the lifecycle column.
        self::assertStringContainsString('Activo', $content);
    }

    public function testRegisterExportHandlesDocumentWithoutCurrentVersion(): void
    {
        $client = static::createClient();
        $em = $this->em();
        // Realistic register row: a form with only a DRAFT revision has no in-force version, so the
        // revision and date columns must come out empty without blowing up the stream.
        $document = (new Document())->setCode('F.99.0')->setTitle('Formato sin emitir')->setType(DocumentType::FORM)->setStatus(ObligationStatus::PENDING);
        $document->addVersion((new DocumentVersion())->setRevisionNumber(0)->setStatus(VersionStatus::DRAFT));
        $em->persist($document);
        $reader = (new User())->setFullName('Lectora')->setEmail('lectora-export-novers@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        $content = $this->captureCsv($client, '/documentos/export.csv');

        self::assertResponseIsSuccessful();
        // No in-force revision: the row ends with empty rev/date columns, then status and lifecycle.
        self::assertStringContainsString('F.99.0;Formato sin emitir;Formato;;;;;', $content);
    }

    /**
     * Requests the CSV export and returns its body.
     *
     * @return string the response body
     */
    private function captureCsv(object $client, string $uri): string
    {
        $client->request('GET', $uri);

        return (string) $client->getResponse()->getContent();
    }

    public function testAdminCancelsDocumentWithReason(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistDocument($em, 'PC.07.0');
        $this->loginAdmin($client, $em, 'admin-cancel@example.test');

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Anular', ['reason' => 'Creado por error']);

        self::assertResponseRedirects('/documentos/'.$document->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.lifecycle-banner', 'Anulado');
        self::assertSelectorTextContains('.lifecycle-banner', 'Creado por error');
    }

    public function testCancelWithoutReasonIsRejected(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistDocument($em, 'PC.08.0');
        $this->loginAdmin($client, $em, 'admin-noreason@example.test');

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Anular', ['reason' => '']);

        $client->followRedirect();
        // Rejected: it stays active (no banner) and the reason is demanded.
        self::assertSelectorNotExists('.lifecycle-banner');
        self::assertSelectorTextContains('.flash', 'motivo');
    }

    public function testAdminArchivesDocument(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistDocument($em, 'PC.10.0');
        $this->loginAdmin($client, $em, 'admin-archive@example.test');

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Archivar', ['reason' => 'Ya no aplica']);

        $client->followRedirect();
        self::assertSelectorTextContains('.lifecycle-banner', 'Archivado');
    }

    public function testAdminRestoresArchivedDocument(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistDocument($em, 'PC.11.0')->archive('Obsoleto');
        $this->loginAdmin($client, $em, 'admin-restore@example.test');

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Reactivar documento');

        $client->followRedirect();
        // Back to active: no lifecycle banner.
        self::assertSelectorNotExists('.lifecycle-banner');
    }

    public function testNonAdminCannotChangeLifecycle(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistDocument($em, 'PC.09.0');
        $reader = (new User())->setFullName('Lectora')->setEmail('lectora5@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        // Access is denied before any CSRF check, so no token is needed to assert the gate.
        $client->request('POST', '/documentos/'.$document->getId().'/estado', ['action' => 'cancel', 'reason' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRsgmaCancelsDocument(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistDocument($em, 'PC.12.0');
        // The RSGMA (ems_manager) owns document control: they can cancel/archive without being admin.
        $role = (new Role())->setCode('ems_manager')->setName('Responsable del SGA');
        $em->persist($role);
        $rsgma = (new User())->setFullName('Carlos SGA')->setEmail('sga-lifecycle@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($rsgma);
        $em->flush();
        $client->loginUser($rsgma);

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Anular', ['reason' => 'Sustituido por nueva versión']);

        self::assertResponseRedirects('/documentos/'.$document->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.lifecycle-banner', 'Anulado');
    }

    /** Persists a procedure owned by the given responsible role. */
    private function persistProcedure(EntityManagerInterface $em, string $code, Role $responsible): Document
    {
        $em->persist($responsible);
        $document = (new Document())->setCode($code)->setTitle('Procedimiento '.$code)->setType(DocumentType::PROCEDURE)->setStatus(ObligationStatus::DONE)->setResponsibleRole($responsible);
        $em->persist($document);

        return $document;
    }

    public function testResponsibleCanIssueRevision(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $role = (new Role())->setCode('ems_manager')->setName('Responsable del SGA');
        $document = $this->persistProcedure($em, 'PG-06.01', $role);
        $user = (new User())->setFullName('Carlos SGA')->setEmail('sga-issue@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Nueva revisión', ['changeSummary' => 'Actualización anual']);

        self::assertResponseRedirects('/documentos/'.$document->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('tbody', 'Borrador');
    }

    public function testNonResponsibleCannotIssueRevision(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistProcedure($em, 'PG-06.02', (new Role())->setCode('ems_manager')->setName('RSGMA'));
        $other = (new Role())->setCode('secretary')->setName('Secretaría');
        $em->persist($other);
        $user = (new User())->setFullName('Ana')->setEmail('sec-issue@example.test')->setActive(true)->addAssignedRole($other);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // The voter runs before the CSRF check, so the 403 here is the authorization gate.
        $client->request('POST', '/documentos/'.$document->getId().'/revision', ['changeSummary' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApproverApprovesRevisionAndSupersedes(): void
    {
        $client = static::createClient();
        $em = $this->em();
        // A procedure is approved by Dirección (PC.01.0).
        $document = $this->persistProcedure($em, 'PG-06.03', (new Role())->setCode('ems_manager')->setName('RSGMA'));
        $direction = (new Role())->setCode('direction')->setName('Dirección');
        $em->persist($direction);
        $approver = (new User())->setFullName('Marta Directora')->setEmail('direccion-approve@example.test')->setActive(true)->addAssignedRole($direction);
        $em->persist($approver);

        // Revision 0 already approved and in force; revision 1 is the draft we approve now.
        $rev0 = (new DocumentVersion())->setRevisionNumber(0)->setStatus(VersionStatus::APPROVED)->setChangeSummary('Inicial.');
        $rev0->addApprovalEvent((new ApprovalEvent())->setApprover($approver)->setIntegrityHash('seed0'));
        $rev1 = (new DocumentVersion())->setRevisionNumber(1)->setStatus(VersionStatus::DRAFT)->setChangeSummary('Actualización.');
        $document->addVersion($rev0);
        $document->addVersion($rev1);
        $em->persist($rev0);
        $em->persist($rev1);
        $em->flush();
        $client->loginUser($approver);

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Aprobar');

        $client->followRedirect();
        // Revision 1 is now in force; revision 0 was superseded to obsolete.
        self::assertSelectorTextContains('.is-in-force', 'En vigor');
        self::assertSelectorTextContains('body', 'Obsoleta');
    }

    public function testWrongRoleCannotApproveRevision(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistProcedure($em, 'PG-06.04', (new Role())->setCode('ems_manager')->setName('RSGMA'));
        $version = (new DocumentVersion())->setRevisionNumber(0)->setStatus(VersionStatus::DRAFT)->setChangeSummary('Edición inicial.');
        $document->addVersion($version);
        $em->persist($version);
        // Quality is NOT the approver of a procedure (Dirección is).
        $quality = (new Role())->setCode('quality')->setName('Calidad');
        $em->persist($quality);
        $user = (new User())->setFullName('Lucía')->setEmail('calidad-approve@example.test')->setActive(true)->addAssignedRole($quality);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // The voter runs before the CSRF check, so the 403 here is the authorization gate.
        $client->request('POST', '/documentos/'.$document->getId().'/revision/'.$version->getId().'/aprobar');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Persists a procedure with a draft revision pending Dirección's approval.
     *
     * @return array{0: Document, 1: DocumentVersion, 2: User} the document, its draft revision and the approver
     */
    private function procedurePendingApproval(EntityManagerInterface $em, string $code, string $approverEmail): array
    {
        $document = $this->persistProcedure($em, $code, (new Role())->setCode('ems_manager')->setName('RSGMA'));
        $direction = (new Role())->setCode('direction')->setName('Dirección');
        $em->persist($direction);
        $approver = (new User())->setFullName('Marta Directora')->setEmail($approverEmail)->setActive(true)->addAssignedRole($direction);
        $em->persist($approver);
        $version = (new DocumentVersion())->setRevisionNumber(0)->setStatus(VersionStatus::DRAFT)->setChangeSummary('Edición inicial.');
        $document->addVersion($version);
        $em->persist($version);
        $em->flush();

        return [$document, $version, $approver];
    }

    public function testApprovingSealsOfficialPdfAndHashesItsBytes(): void
    {
        $client = static::createClient();
        $em = $this->em();
        [$document, $version, $approver] = $this->procedurePendingApproval($em, 'PG-06.10', 'direccion-seal@example.test');
        $versionId = $version->getId();
        $client->loginUser($approver);

        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Aprobar');

        $em->clear();
        $reloaded = $em->find(DocumentVersion::class, $versionId);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getStoragePath(), 'Approval must persist the generated PDF path.');
        $approval = $reloaded->getLatestApproval();
        self::assertNotNull($approval);

        $uploader = static::getContainer()->get(FileUploader::class);
        $absolute = $uploader->absolutePath((string) $reloaded->getStoragePath());
        self::assertFileExists($absolute);
        $bytes = (string) file_get_contents($absolute);
        self::assertStringStartsWith('%PDF', $bytes);
        // The integrity hash certifies the exact stored bytes (tamper-evidence over the sealed PDF).
        self::assertSame(hash('sha256', $bytes), $approval->getIntegrityHash());
    }

    public function testDownloadPdfPreviewsADraftRevision(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $document = $this->persistDocument($em, 'PC.30.0');
        $version = (new DocumentVersion())->setRevisionNumber(0)->setStatus(VersionStatus::DRAFT)->setChangeSummary('Borrador.');
        $document->addVersion($version);
        $em->persist($version);
        $reader = (new User())->setFullName('Lectora')->setEmail('lectora-pdf@example.test')->setActive(true);
        $em->persist($reader);
        $em->flush();
        $client->loginUser($reader);

        $client->request('GET', '/documentos/'.$document->getId().'/revision/'.$version->getId().'/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());
    }

    public function testApproverAttachesSignedPdf(): void
    {
        $client = static::createClient();
        $em = $this->em();
        [$document, $version, $approver] = $this->procedurePendingApproval($em, 'PG-06.11', 'direccion-sign@example.test');
        $client->loginUser($approver);

        // Approve first: the signature can only be attached to an already-approved revision.
        $client->request('GET', '/documentos/'.$document->getId());
        $client->submitForm('Aprobar');
        $crawler = $client->followRedirect();

        $signedPath = tempnam(sys_get_temp_dir(), 'signed').'.pdf';
        file_put_contents($signedPath, "%PDF-1.4\nfirmado por la directora\n%%EOF");
        $form = $crawler->selectButton('Adjuntar firma')->form();
        $field = $form['signedPdf'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($signedPath);
        $client->submit($form);

        self::assertResponseRedirects('/documentos/'.$document->getId());
        $client->followRedirect();
        // The trail now offers the signed PDF and flags the revision as signed.
        self::assertSelectorExists('a:contains("PDF firmado")');

        $em->clear();
        $reloaded = $em->find(DocumentVersion::class, $version->getId());
        self::assertNotNull($reloaded?->getLatestApproval()?->getSignedPdfPath());
    }
}
