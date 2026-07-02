<?php

declare(strict_types=1);

namespace App\Controller;

use App\Help\HelpRegistry;
use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the online help: the index of topics, each topic's full explanation page, and the small
 * HTML fragment that help.js loads into the popover.
 *
 * The help itself is not area-restricted (it explains how the app works and what the law requires),
 * so any authenticated user may read it; the firewall's ^/ → ROLE_USER rule already enforces that.
 */
#[Route('/ayuda')]
class HelpController extends AbstractController
{
    public function __construct(private readonly HelpRegistry $registry)
    {
    }

    /**
     * The catalogue of every help topic, as an entry point when no specific screen is in context.
     */
    #[Route('', name: 'help_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('help/index.html.twig', [
            'topics' => $this->registry->all(),
        ]);
    }

    /**
     * The full help page for a topic: the body, its legal references and links to the SGA documents
     * that back it. Referenced documents are resolved in one batch query; a code with no live
     * document is still shown, just without a link.
     */
    #[Route('/{slug}', name: 'help_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, DocumentRepository $documents): Response
    {
        $topic = $this->registry->bySlug($slug);
        if (null === $topic) {
            throw $this->createNotFoundException('No existe ese tema de ayuda.');
        }

        return $this->render('help/show.html.twig', [
            'topic' => $topic,
            'documentsByCode' => $documents->findByCodes($topic->docCodes),
        ]);
    }

    /**
     * The popover fragment (title, summary, legal references and a link to the full page) that
     * help.js fetches and injects. Rendered without the application shell: it is embedded in a modal.
     */
    #[Route('/{slug}/panel', name: 'help_panel', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function panel(string $slug): Response
    {
        $topic = $this->registry->bySlug($slug);
        if (null === $topic) {
            throw $this->createNotFoundException('No existe ese tema de ayuda.');
        }

        return $this->render('help/_panel.html.twig', ['topic' => $topic]);
    }
}
