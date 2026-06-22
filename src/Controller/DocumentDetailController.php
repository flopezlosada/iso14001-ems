<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Enum\DocumentType;
use App\Repository\DocumentRepository;
use App\Service\AuditLogger;
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
 * documented information that the app already records but never showed. Issuing/approving revisions
 * is a separate workflow, not part of these views.
 *
 * Reading is granted to any authenticated user (ROLE_USER), with no area gating: the document
 * register is a transparency artefact, consistent with the obligations cockpit. Changing a
 * document's lifecycle (cancel/archive/restore) is restricted to ROLE_ADMIN for now — widening it
 * to the RSGMA role is a follow-up once that maps to a permission.
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
}
