<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ApprovalEvent;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\VersionStatus;
use App\Repository\DocumentRepository;
use App\Security\Voter\DocumentVoter;
use App\Service\AuditLogger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function __construct(private readonly AuditLogger $auditLogger)
    {
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
        // Order by the PC.01.0 type taxonomy (the declaration order of DocumentType), then by code,
        // rather than the alphabetical enum value an SQL ORDER BY would give.
        $rank = array_flip(array_map(static fn (DocumentType $t): string => $t->value, DocumentType::cases()));
        $register = $documents->findForRegister();
        usort($register, static fn (Document $a, Document $b): int => [$rank[$a->getType()->value], $a->getCode() ?? '']
            <=> [$rank[$b->getType()->value], $b->getCode() ?? '']);

        return $this->render('document/register.html.twig', [
            'documents' => $register,
        ]);
    }

    /**
     * Renders the document detail with its version history (newest revision first).
     *
     * @param Document $document the document to show, resolved from the {id} route parameter
     *
     * @return Response the rendered detail page
     */
    #[Route('/documentos/{id}', name: 'document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document): Response
    {
        $versions = $document->getVersions()->toArray();
        usort($versions, static fn (DocumentVersion $a, DocumentVersion $b): int => $b->getRevisionNumber() <=> $a->getRevisionNumber());

        return $this->render('document/show.html.twig', [
            'document' => $document,
            'versions' => $versions,
            'current' => $document->getCurrentVersion(),
            'moduleRoute' => $document->getLinkedArea()?->indexRoute(),
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
            ->setChangeSummary($summary);
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
        // Approvable from DRAFT (and IN_REVIEW once a review step exists); never re-approve.
        if (\in_array($version->getStatus(), [VersionStatus::APPROVED, VersionStatus::OBSOLETE], true)) {
            $this->addFlash('error', 'Esa revisión no está pendiente de aprobación.');

            return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
        }

        // The revision currently in force is superseded by the newly approved one.
        $previous = $document->getCurrentVersion();
        if (null !== $previous && $previous !== $version) {
            $previous->setStatus(VersionStatus::OBSOLETE);
        }

        $version->setStatus(VersionStatus::APPROVED);
        $event = (new ApprovalEvent())
            ->setApprover($approver)
            ->setIntegrityHash($this->integrityHash($document, $version));
        $version->addApprovalEvent($event);

        $em->persist($event);
        $em->flush();
        $this->auditLogger->log('document.revision_approved', 'Document', (string) $document->getId(), 'Aprobada revisión '.$version->getRevisionNumber());
        $this->addFlash('success', 'Revisión '.$version->getRevisionNumber().' aprobada y en vigor.');

        return $this->redirectToRoute('document_show', ['id' => $document->getId()]);
    }

    /**
     * Tamper-evidence hash of an approved revision. With no generated PDF yet, it hashes a canonical
     * representation of the revision; once PDF generation exists this should hash the PDF itself.
     */
    private function integrityHash(Document $document, DocumentVersion $version): string
    {
        return hash('sha256', implode('|', [
            // The document id (immutable), not its code (which could be edited and break the hash).
            (string) $document->getId(),
            '#'.$version->getRevisionNumber(),
            $version->getIssueDate()->format('Y-m-d'),
            (string) $version->getAuthor(),
            (string) $version->getChangeSummary(),
        ]));
    }
}
