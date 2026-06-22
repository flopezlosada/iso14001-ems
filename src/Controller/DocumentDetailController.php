<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only detail of a single document from the register (the live F.01): its identification, the
 * revision currently in force, and the full revision history with the approval trail — who approved
 * each version, when, the integrity hash, and whether a signed PDF is attached. This is the auditable
 * face of clause 7.5: it surfaces the control of documented information that the app already records
 * but never showed. Issuing/approving revisions is a separate workflow, not part of this view.
 *
 * Access is intentionally granted to any authenticated user (ROLE_USER), with no area gating: the
 * document register is a transparency artefact, consistent with the obligations cockpit. The
 * AreaVoter only guards write operations on module-specific data, not this read-only register view.
 */
final class DocumentDetailController extends AbstractController
{
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
