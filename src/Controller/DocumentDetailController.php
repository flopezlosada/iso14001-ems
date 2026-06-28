<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ApprovalEvent;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\VersionStatus;
use App\Repository\AuditLogRepository;
use App\Repository\DocumentRepository;
use App\Security\Voter\DocumentVoter;
use App\Service\AuditLogger;
use App\Service\Document\DocumentPdfGenerator;
use App\Service\FileUploader;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only views of the document register (the live F.01): the master list of every document and
 * the detail of one — its identification, the revision currently in force, and the full revision
 * history with the approval trail (who approved each version, when, the integrity hash, and whether
 * a signed PDF is attached). This is the auditable face of clause 7.5: it surfaces the control of
 * documented information that the app already records but never showed.
 *
 * Reading is granted to any authenticated user (ROLE_USER), with no area gating: the document
 * register is a transparency artefact, consistent with the obligations cockpit. The write actions
 * are gated: lifecycle changes (cancel/archive/restore) need {@see DocumentVoter::LIFECYCLE} (the
 * RSGMA, owner of document control); issuing a revision needs {@see DocumentVoter::ISSUE} (the
 * responsible role); approving needs {@see DocumentVoter::APPROVE} (the role that approves that
 * document type, PC.01.0).
 */
final class DocumentDetailController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentPdfGenerator $pdfGenerator,
        private readonly FileUploader $fileUploader,
        #[Target('app.document_body')]
        private readonly HtmlSanitizerInterface $htmlSanitizer,
    ) {
    }

    /**
     * Sanitises the rich-text body coming from the editor into safe HTML, treating an empty editor
     * as null. Never trust the editor's HTML: it is rendered later with |raw, so it must be cleaned
     * of scripts and dangerous attributes here, at the trust boundary.
     *
     * @param mixed $raw the raw "body" request value
     *
     * @return string|null the sanitised HTML, or null when there is no real content
     */
    private function cleanBody(mixed $raw): ?string
    {
        $html = \is_string($raw) ? trim($raw) : '';
        if ('' === $html) {
            return null;
        }
        $clean = trim($this->htmlSanitizer->sanitize($html));

        // Trix emits an empty wrapper for a blank editor; treat "no visible text" as no body.
        return '' === trim(strip_tags($clean)) ? null : $clean;
    }

    /**
     * Guards a write action against an inactive (cancelled/archived) document: returns a redirect to
     * abort, or null to proceed. The GET pages already block this; the POST endpoints must too.
     *
     * @param Document $document the document being acted on
     *
     * @return Response|null a redirect if the document is not active, null otherwise
     */
    private function abortIfInactive(Document $document): ?Response
    {
        if ($document->isActive()) {
            return null;
        }
        $this->addFlash('error', 'Solo se pueden gestionar revisiones de un documento activo.');

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    /**
     * The document register (F.01): every document with its in-force revision, for searching and
     * navigating to each detail. Includes the manual and procedures that the obligations cockpit
     * (which only lists chapter-bound obligations) does not show.
     *
     * @param DocumentRepository $documents the document register repository
     *
     * @return Response the rendered register list
     */
    #[Route('/documentos', name: 'document_index', methods: ['GET'])]
    public function index(DocumentRepository $documents): Response
    {
        return $this->render('document/register.html.twig', [
            'documents' => $this->orderedRegister($documents),
        ]);
    }

    /**
     * Downloads the full document register (F.01) as a CSV file, with the same columns and order
     * the on-screen register shows. It is the offline/printable face of the live register: the
     * artefact the auditor can be handed, replacing the legacy F.01 spreadsheet.
     *
     * Open to any authenticated user (ROLE_USER), consistent with the register being a transparency
     * artefact with no area gating. The export itself is logged (clause 7.5).
     *
     * The register is small and bounded (the centre's document catalogue), so the CSV is built in
     * memory and returned as a plain response rather than streamed — simpler and consistent with the
     * other downloads in this controller.
     *
     * @param DocumentRepository $documents the document register repository
     *
     * @return Response the CSV file attachment
     */
    #[Route('/documentos/export.csv', name: 'document_register_export', methods: ['GET'])]
    public function exportCsv(DocumentRepository $documents): Response
    {
        $register = $this->orderedRegister($documents);
        $this->auditLogger->log(
            'document.register.export',
            'Document',
            null,
            sprintf('Exportación del registro documental F.01 (%d documentos)', count($register)),
        );

        $buffer = fopen('php://temp', 'r+b');
        // UTF-8 BOM so Excel (Windows, Spanish locale) detects the encoding instead of mojibake.
        fwrite($buffer, "\xEF\xBB\xBF");
        // Semicolon separator: what Excel expects under a Spanish locale, so each field lands in its
        // own column instead of all in the first one.
        fputcsv($buffer, ['Código', 'Documento', 'Tipo', 'Área', 'Responsable', 'Revisión en vigor', 'Fecha en vigor', 'Estado', 'Ciclo de vida'], ';');
        foreach ($register as $document) {
            $current = $document->getCurrentVersion();
            fputcsv($buffer, [
                $document->getCode() ?? '',
                $document->getTitle(),
                $document->getType()->label(),
                $document->getLinkedArea()?->label() ?? '',
                $document->getResponsibleRole()?->getName() ?? '',
                null !== $current ? 'Rev. '.$current->getRevisionNumber() : '',
                null !== $current ? $current->getIssueDate()->format('d/m/Y') : '',
                $document->getStatus()->label(),
                // The CSV is handed to the auditor on its own, so spell out the lifecycle for every
                // row rather than leaving active documents blank as the on-screen table does.
                $document->isActive() ? 'Activo' : $document->getLifecycle()->label(),
            ], ';');
        }
        rewind($buffer);
        $csv = (string) stream_get_contents($buffer);
        fclose($buffer);

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'registro-documental-F01.csv',
        ));
        // Authenticated download on the centre's shared computers: never let it sit in the cache.
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * Loads the full document register ordered by the PC.01.0 type taxonomy (the declaration order
     * of {@see DocumentType}) and then by code — the order a plain SQL ORDER BY cannot give. Shared
     * by the on-screen register and the CSV export so both stay in lockstep.
     *
     * @param DocumentRepository $documents the document register repository
     *
     * @return Document[] the register, ordered for presentation
     */
    private function orderedRegister(DocumentRepository $documents): array
    {
        $rank = array_flip(array_map(static fn (DocumentType $t): string => $t->value, DocumentType::cases()));
        $register = $documents->findForRegister();
        usort($register, static fn (Document $a, Document $b): int => [$rank[$a->getType()->value], $a->getCode() ?? '']
            <=> [$rank[$b->getType()->value], $b->getCode() ?? '']);

        return $register;
    }

    /**
     * Renders the document detail with its version history (newest revision first) and, for a
     * periodic obligation, the trail of closed period reviews (from the audit log).
     *
     * @param Document           $document  the document to show, resolved from the {id} route parameter
     * @param AuditLogRepository $auditLogs to list this obligation's period-review closures
     *
     * @return Response the rendered detail page
     */
    #[Route('/documentos/{id}', name: 'document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document, AuditLogRepository $auditLogs): Response
    {
        $versions = $document->getVersions()->toArray();
        usort($versions, static fn (DocumentVersion $a, DocumentVersion $b): int => $b->getRevisionNumber() <=> $a->getRevisionNumber());

        // Whether an unapproved revision is already open (draft/in-review): while one exists, a new
        // revision is not offered — that open one is finished first (one live revision at a time).
        $hasOpenRevision = false;
        foreach ($versions as $candidate) {
            if ($candidate->getStatus()->isEditable()) {
                $hasOpenRevision = true;
                break;
            }
        }

        // The body to show on the detail: the in-force revision's, or — while none is approved yet —
        // the most recent revision that has a body, so a drafted document is visible before approval.
        $current = $document->getCurrentVersion();
        $displayVersion = (null !== $current && null !== $current->getBody()) ? $current : null;
        if (null === $displayVersion) {
            foreach ($versions as $candidate) {
                if (null !== $candidate->getBody()) {
                    $displayVersion = $candidate;
                    break;
                }
            }
        }

        return $this->render('document/show.html.twig', [
            'document' => $document,
            'versions' => $versions,
            'current' => $current,
            'displayVersion' => $displayVersion,
            'hasOpenRevision' => $hasOpenRevision,
            'moduleRoute' => $document->getLinkedArea()?->indexRoute(),
            // The trail of "marked done for the period" events, so the responsible can see when each
            // review cycle was closed without a parallel data model (it lives in the audit log).
            'reviews' => $auditLogs->findForSubject('Document', (string) $document->getId(), 'obligation.completed'),
        ]);
    }

    /**
     * Changes a document's lifecycle (cancel / archive / restore), append-only and audited. A
     * document is never deleted: a mistaken one is cancelled with a reason, a retired one archived.
     *
     * @param Request                $request  the POST request (action, reason, CSRF token)
     * @param Document               $document the document to act on
     * @param EntityManagerInterface $em       to persist the state change
     *
     * @return Response a redirect back to the document detail
     */
    #[Route('/documentos/{id}/estado', name: 'document_lifecycle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changeLifecycle(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::LIFECYCLE, $document);
        if (!$this->isCsrfTokenValid('document_lifecycle'.(string) $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $action = (string) $request->request->get('action');
        $reason = trim((string) $request->request->get('reason'));
        $redirect = $this->redirectToRoute('document_show', ['id' => $document->getId()]);

        if ('cancel' === $action) {
            if ('' === $reason) {
                $this->addFlash('error', 'Indica el motivo de la anulación.');

                return $redirect;
            }
            $document->cancel($reason);
            [$logAction, $summary, $flash] = ['document.cancelled', 'Anulado: '.$reason, 'Documento anulado.'];
        } elseif ('archive' === $action) {
            $document->archive('' !== $reason ? $reason : null);
            [$logAction, $summary, $flash] = ['document.archived', '' !== $reason ? 'Archivado: '.$reason : 'Archivado', 'Documento archivado.'];
        } elseif ('restore' === $action) {
            $document->restore();
            [$logAction, $summary, $flash] = ['document.restored', 'Reactivado', 'Documento reactivado.'];
        } else {
            throw $this->createNotFoundException('Acción de ciclo de vida desconocida.');
        }

        $em->flush();
        // Audit AFTER the business flush, per the project convention.
        $this->auditLogger->log($logAction, 'Document', (string) $document->getId(), $summary);
        $this->addFlash('success', $flash);

        return $redirect;
    }

    /**
     * Confirmation page for a destructive lifecycle action (cancel / archive): shows what it means
     * and collects the reason, instead of an inline input next to the button. The actual change is
     * still done by {@see changeLifecycle} (POST). Restore needs no reason, so it stays inline.
     *
     * @param Document $document the document to act on
     * @param string   $action   the lifecycle action: "cancel" or "archive"
     *
     * @return Response the confirmation page, or a redirect if the document is not active
     */
    #[Route('/documentos/{id}/ciclo/{action}', name: 'document_lifecycle_confirm', requirements: ['id' => '\d+', 'action' => 'cancel|archive'], methods: ['GET'])]
    public function confirmLifecycle(Document $document, string $action): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::LIFECYCLE, $document);
        if (!$document->isActive()) {
            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        return $this->render('document/lifecycle_confirm.html.twig', [
            'document' => $document,
            'action' => $action,
        ]);
    }

    /**
     * Issues a new revision of the document as a DRAFT (revision = highest + 1). Done by the
     * responsible role (the elaborator); approval is a separate step.
     *
     * @param Request                $request  the POST request (changeSummary, CSRF token)
     * @param Document               $document the document to add a revision to
     * @param EntityManagerInterface $em       to persist the new revision
     *
     * @return Response a redirect back to the document detail
     */
    #[Route('/documentos/{id}/revision', name: 'document_revision_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function issueRevision(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::ISSUE, $document);
        if (!$this->isCsrfTokenValid('document_revision'.(string) $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        if (null !== ($abort = $this->abortIfInactive($document))) {
            return $abort;
        }

        $summary = trim((string) $request->request->get('changeSummary'));
        if ('' === $summary) {
            $this->addFlash('error', 'Describe brevemente los cambios de la nueva revisión.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        $nextNumber = 0;
        foreach ($document->getVersions() as $existing) {
            $nextNumber = max($nextNumber, $existing->getRevisionNumber() + 1);
        }

        $author = $this->getUser();
        $version = (new DocumentVersion())
            ->setRevisionNumber($nextNumber)
            ->setIssueDate(new \DateTimeImmutable('today'))
            ->setStatus(VersionStatus::DRAFT)
            ->setAuthor($author instanceof User ? $author->getFullName() : null)
            ->setChangeSummary($summary)
            ->setBody($this->cleanBody($request->request->get('body')));
        $document->addVersion($version);

        try {
            $em->persist($version);
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            // Two drafts of the same revision number raced (the unique constraint caught it).
            $this->addFlash('error', 'Se creó otra revisión a la vez. Recarga la página e inténtalo de nuevo.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }
        $this->auditLogger->log('document.revision_drafted', 'Document', (string) $document->getId(), 'Borrador de revisión '.$nextNumber);
        $this->addFlash('success', 'Nueva revisión '.$nextNumber.' creada como borrador.');

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    /**
     * Editor page to compose a NEW revision: shows the rich-text editor pre-filled with the body of
     * the revision currently in force, so a new revision starts from the live text and is edited,
     * not written from scratch. If an open draft already exists, redirects to editing it (PC.01.0
     * allows only one draft at a time per the revision-number unique constraint).
     *
     * @param Document $document the document to draft a revision for
     *
     * @return Response the editor page, or a redirect
     */
    #[Route('/documentos/{id}/revision/nueva', name: 'document_revision_compose', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function composeRevision(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::ISSUE, $document);
        if (!$document->isActive()) {
            $this->addFlash('error', 'Solo se pueden emitir revisiones de un documento activo.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        $inForceBody = null;
        foreach ($document->getVersions() as $existing) {
            if ($existing->getStatus()->isEditable()) {
                // An unapproved revision is already open: edit it instead of starting another.
                return $this->redirectToRoute('document_revision_edit', ['id' => $document->getId(), 'versionId' => $existing->getId()]);
            }
            if ($existing->isInForce()) {
                $inForceBody = $existing->getBody();
            }
        }

        return $this->render('document/revision_edit.html.twig', [
            'document' => $document,
            'version' => null,
            'formAction' => $this->generateUrl('document_revision_new', ['id' => $document->getId()]),
            'body' => $inForceBody,
            'changeSummary' => null,
        ]);
    }

    /**
     * Editor page to edit an existing DRAFT revision's body and change summary. Only drafts are
     * editable; an approved or obsolete revision is immutable.
     *
     * @param Document               $document  the document
     * @param int                    $versionId the draft revision to edit
     * @param EntityManagerInterface $em        to resolve the revision
     *
     * @return Response the editor page, or a redirect
     */
    #[Route('/documentos/{id}/revision/{versionId}/editar', name: 'document_revision_edit', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['GET'])]
    public function editRevision(Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::ISSUE, $document);
        $version = $this->resolveVersion($document, $versionId, $em);
        if (!$version->getStatus()->isEditable()) {
            $this->addFlash('error', 'Solo se puede editar una revisión que no esté aprobada.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        return $this->render('document/revision_edit.html.twig', [
            'document' => $document,
            'version' => $version,
            'formAction' => $this->generateUrl('document_revision_update', ['id' => $document->getId(), 'versionId' => $version->getId()]),
            'body' => $version->getBody(),
            'changeSummary' => $version->getChangeSummary(),
        ]);
    }

    /**
     * Saves edits to a DRAFT revision (body + change summary). Only drafts are mutable.
     *
     * @param Request                $request   the POST request (changeSummary, body, CSRF token)
     * @param Document               $document  the document
     * @param int                    $versionId the draft revision to update
     * @param EntityManagerInterface $em        to persist the changes
     *
     * @return Response a redirect back to the document detail
     */
    #[Route('/documentos/{id}/revision/{versionId}', name: 'document_revision_update', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['POST'])]
    public function updateRevision(Request $request, Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::ISSUE, $document);
        if (!$this->isCsrfTokenValid('document_revision'.(string) $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        if (null !== ($abort = $this->abortIfInactive($document))) {
            return $abort;
        }

        $version = $this->resolveVersion($document, $versionId, $em);
        if (!$version->getStatus()->isEditable()) {
            $this->addFlash('error', 'Solo se puede editar una revisión que no esté aprobada.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        $summary = trim((string) $request->request->get('changeSummary'));
        if ('' === $summary) {
            $this->addFlash('error', 'Describe brevemente los cambios de la revisión.');

            return $this->redirectToRoute('document_revision_edit', ['id' => $document->getId(), 'versionId' => $version->getId()]);
        }

        $version->setChangeSummary($summary)
            ->setBody($this->cleanBody($request->request->get('body')))
            ->clearReview(); // editing invalidates any prior review: it must be reviewed again.
        $em->flush();
        $this->auditLogger->log('document.revision_edited', 'Document', (string) $document->getId(), 'Edición de la revisión '.$version->getRevisionNumber());
        $this->addFlash('success', 'Revisión '.$version->getRevisionNumber().' guardada.');

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    /**
     * Sends a DRAFT revision to review (PC.01.0 elaboración → revisión). Done by the elaborator (the
     * responsible role). The revision must have a body: an empty document is not ready for review.
     *
     * @param Request                $request   the POST request (CSRF token)
     * @param Document               $document  the document
     * @param int                    $versionId the draft revision to send to review
     * @param EntityManagerInterface $em        to persist the transition
     *
     * @return Response a redirect back to the document detail
     */
    #[Route('/documentos/{id}/revision/{versionId}/enviar-a-revision', name: 'document_revision_submit', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['POST'])]
    public function submitForReview(Request $request, Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::ISSUE, $document);
        if (!$this->isCsrfTokenValid('document_submit'.(string) $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }
        if (null !== ($abort = $this->abortIfInactive($document))) {
            return $abort;
        }

        $version = $this->resolveVersion($document, $versionId, $em);
        if (VersionStatus::DRAFT !== $version->getStatus()) {
            $this->addFlash('error', 'Solo se puede enviar a revisión un borrador.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }
        if (null === $version->getBody()) {
            $this->addFlash('error', 'Redacta el contenido del documento antes de enviarlo a revisión.');

            return $this->redirectToRoute('document_revision_edit', ['id' => $document->getId(), 'versionId' => $version->getId()]);
        }

        $version->setStatus(VersionStatus::IN_REVIEW);
        $em->flush();
        $this->auditLogger->log('document.revision_submitted', 'Document', (string) $document->getId(), 'Revisión '.$version->getRevisionNumber().' enviada a revisión');
        $this->addFlash('success', 'Revisión '.$version->getRevisionNumber().' enviada a revisión.');

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    /**
     * Records the review of a revision (PC.01.0 revisión step), done by the Responsable del Sistema
     * (RSGMA). The revision must be in review; once reviewed it can be approved.
     *
     * @param Request                $request   the POST request (CSRF token)
     * @param Document               $document  the document
     * @param int                    $versionId the revision to mark as reviewed
     * @param EntityManagerInterface $em        to persist the review
     *
     * @return Response a redirect back to the document detail
     */
    #[Route('/documentos/{id}/revision/{versionId}/revisar', name: 'document_revision_review', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['POST'])]
    public function reviewRevision(Request $request, Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::REVIEW, $document);
        if (!$this->isCsrfTokenValid('document_review'.(string) $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }
        if (null !== ($abort = $this->abortIfInactive($document))) {
            return $abort;
        }

        $version = $this->resolveVersion($document, $versionId, $em);
        if (VersionStatus::IN_REVIEW !== $version->getStatus()) {
            $this->addFlash('error', 'Solo se puede revisar una revisión que esté en revisión.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        $reviewer = $this->getUser();
        $version->review($reviewer instanceof User ? $reviewer->getFullName() : 'Revisor', new \DateTimeImmutable());
        $em->flush();
        $this->auditLogger->log('document.revision_reviewed', 'Document', (string) $document->getId(), 'Revisión '.$version->getRevisionNumber().' revisada');
        $this->addFlash('success', 'Revisión '.$version->getRevisionNumber().' marcada como revisada. Ya puede aprobarse.');

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    /**
     * Approves a draft revision (PC.01.0): records a tamper-evident {@see ApprovalEvent}, marks the
     * revision in force and supersedes the previous one to OBSOLETE. Restricted to the role that
     * approves this document's type.
     *
     * @param Request                $request   the POST request (CSRF token)
     * @param Document               $document  the document
     * @param int                    $versionId the revision to approve
     * @param EntityManagerInterface $em        to persist the approval
     *
     * @return Response a redirect back to the document detail
     */
    #[Route('/documentos/{id}/revision/{versionId}/aprobar', name: 'document_revision_approve', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['POST'])]
    public function approveRevision(Request $request, Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::APPROVE, $document);
        if (!$this->isCsrfTokenValid('document_approve'.(string) $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $version = $em->find(DocumentVersion::class, $versionId);
        if (null === $version || $version->getDocument() !== $document) {
            throw $this->createNotFoundException('Revisión no encontrada.');
        }
        $approver = $this->getUser();
        if (!$approver instanceof User) {
            throw $this->createAccessDeniedException();
        }
        // PC.01.0 flow: a revision can only be approved once it is in review AND has been reviewed.
        // This forbids approving a raw draft (it must be sent to review and reviewed first) and
        // re-approving an already in-force/obsolete revision.
        if (VersionStatus::IN_REVIEW !== $version->getStatus() || !$version->isReviewed()) {
            $this->addFlash('error', 'Solo se puede aprobar una revisión que esté en revisión y ya revisada.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        // The revision currently in force is superseded by the newly approved one.
        $previous = $document->getCurrentVersion();
        if (null !== $previous && $previous !== $version) {
            $previous->setStatus(VersionStatus::OBSOLETE);
        }

        $version->setStatus(VersionStatus::APPROVED);
        $event = (new ApprovalEvent())->setApprover($approver);
        $version->addApprovalEvent($event);

        // Generate the official PDF (PC.01.0), persist it as the sealed artefact and hash its bytes:
        // the integrity hash certifies this exact stored file, which is why it is saved rather than
        // regenerated on demand (dompdf output is not byte-for-byte reproducible).
        $pdf = $this->pdfGenerator->render($document, $version);
        $version->setStoragePath($this->fileUploader->store($pdf, 'document-pdfs', 'pdf'));
        $event->setIntegrityHash(hash('sha256', $pdf));

        $em->persist($event);
        $em->flush();
        $this->auditLogger->log('document.revision_approved', 'Document', (string) $document->getId(), 'Aprobada revisión '.$version->getRevisionNumber());
        $this->addFlash('success', 'Revisión '.$version->getRevisionNumber().' aprobada y en vigor.');

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    /**
     * Serves the official control sheet (PC.01.0) of a revision as a PDF. An approved revision serves
     * the sealed PDF that its integrity hash certifies; a draft gets a live preview so the approver
     * can review the sheet before approving.
     *
     * @param Document               $document  the document
     * @param int                    $versionId the revision to render
     * @param EntityManagerInterface $em        to resolve the revision
     *
     * @return Response the PDF, inline
     */
    #[Route('/documentos/{id}/revision/{versionId}/pdf', name: 'document_revision_pdf', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['GET'])]
    public function downloadPdf(Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $version = $this->resolveVersion($document, $versionId, $em);

        $stored = $version->getStoragePath();
        if (null !== $stored && VersionStatus::APPROVED === $version->getStatus()) {
            return $this->file($this->fileUploader->absolutePath($stored), $this->pdfFilename($document, $version));
        }

        return new Response($this->pdfGenerator->render($document, $version), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfFilename($document, $version).'"',
        ]);
    }

    /**
     * Page to attach the approver's digital signature: explains the AutoFirma flow (download the
     * official PDF → sign it on your machine → upload it) and offers the upload, instead of a bare
     * file input in the history table. The upload itself is {@see uploadSignedPdf}.
     *
     * @param Document               $document  the document
     * @param int                    $versionId the approved revision to sign
     * @param EntityManagerInterface $em        to resolve the revision
     *
     * @return Response the sign page, or a redirect if the revision is not approved
     */
    #[Route('/documentos/{id}/revision/{versionId}/firmar', name: 'document_revision_sign_form', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['GET'])]
    public function signForm(Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::APPROVE, $document);
        $version = $this->resolveVersion($document, $versionId, $em);
        if (null === $version->getLatestApproval()) {
            $this->addFlash('error', 'Solo se puede firmar una revisión ya aprobada.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        return $this->render('document/sign_form.html.twig', [
            'document' => $document,
            'version' => $version,
            'approval' => $version->getLatestApproval(),
        ]);
    }

    /**
     * Attaches the PDF signed by the approver with their own certificate (level 1a, "upload the
     * signed PDF" via AutoFirma). Only an already-approved revision can carry a signature; gated to
     * the role that approves this document's type.
     *
     * @param Request                $request   the POST request (signedPdf file, CSRF token)
     * @param Document               $document  the document
     * @param int                    $versionId the approved revision to sign
     * @param EntityManagerInterface $em        to persist the signature path
     *
     * @return Response a redirect back to the document detail
     */
    #[Route('/documentos/{id}/revision/{versionId}/firma', name: 'document_revision_sign', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['POST'])]
    public function uploadSignedPdf(Request $request, Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::APPROVE, $document);
        if (!$this->isCsrfTokenValid('document_sign'.(string) $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $version = $this->resolveVersion($document, $versionId, $em);
        $approval = $version->getLatestApproval();
        $redirect = $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        if (null === $approval) {
            $this->addFlash('error', 'Solo se puede adjuntar la firma de una revisión ya aprobada.');

            return $redirect;
        }

        $file = $request->files->get('signedPdf');
        if (!$file instanceof UploadedFile || 'application/pdf' !== $file->getMimeType()) {
            $this->addFlash('error', 'Adjunta el PDF firmado (formato PDF).');

            return $redirect;
        }

        $previous = $approval->getSignedPdfPath();
        $approval->setSignedPdfPath($this->fileUploader->upload($file, 'document-signed'));
        if (null !== $previous) {
            $this->fileUploader->remove($previous);
        }

        $em->flush();
        $this->auditLogger->log('document.revision_signed', 'Document', (string) $document->getId(), 'Firma adjunta a la revisión '.$version->getRevisionNumber());
        $this->addFlash('success', 'PDF firmado adjuntado a la revisión '.$version->getRevisionNumber().'.');

        return $redirect;
    }

    /**
     * Serves the signed PDF attached to a revision's approval (level 1a).
     *
     * @param Document               $document  the document
     * @param int                    $versionId the revision whose signed PDF to serve
     * @param EntityManagerInterface $em        to resolve the revision
     *
     * @return Response the signed PDF as a download
     */
    #[Route('/documentos/{id}/revision/{versionId}/firma', name: 'document_revision_signed_download', requirements: ['id' => '\d+', 'versionId' => '\d+'], methods: ['GET'])]
    public function downloadSignedPdf(Document $document, int $versionId, EntityManagerInterface $em): Response
    {
        $version = $this->resolveVersion($document, $versionId, $em);
        $signed = $version->getLatestApproval()?->getSignedPdfPath();
        if (null === $signed) {
            throw $this->createNotFoundException('No hay PDF firmado para esta revisión.');
        }

        return $this->file($this->fileUploader->absolutePath($signed), $this->pdfFilename($document, $version, 'firmado'));
    }

    /**
     * Resolves a revision that belongs to the given document, or 404s.
     */
    private function resolveVersion(Document $document, int $versionId, EntityManagerInterface $em): DocumentVersion
    {
        $version = $em->find(DocumentVersion::class, $versionId);
        if (null === $version || $version->getDocument() !== $document) {
            throw $this->createNotFoundException('Revisión no encontrada.');
        }

        return $version;
    }

    /**
     * A human-readable download name for a revision's PDF, e.g. "PC.01.0-rev2.pdf".
     */
    private function pdfFilename(Document $document, DocumentVersion $version, ?string $suffix = null): string
    {
        $base = $document->getCode() ?? ('documento-'.(string) $document->getId());

        return $base.'-rev'.$version->getRevisionNumber().(null !== $suffix ? '-'.$suffix : '').'.pdf';
    }
}
