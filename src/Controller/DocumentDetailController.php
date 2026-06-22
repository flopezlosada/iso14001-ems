<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Enum\DocumentType;
use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
 * Access is intentionally granted to any authenticated user (ROLE_USER), with no area gating: the
 * document register is a transparency artefact, consistent with the obligations cockpit. The
 * AreaVoter only guards write operations on module-specific data, not this read-only register view.
 */
final class DocumentDetailController extends AbstractController
{
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
}
