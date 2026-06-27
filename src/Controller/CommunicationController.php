<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Communication;
use App\Enum\Area;
use App\Form\CommunicationType;
use App\Repository\CommunicationRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Register of environmental communications (RG-07.04.00, procedure PC.04.0, ISO 14001:2015 §7.4):
 * internal/external communications, queries, suggestions and complaints from interested parties. A
 * plain CRUD module backed by a flat event log (newest first), with no scoring engine.
 *
 * Requires authentication and per-area permission (Area::COMMUNICATION): READ to view, WRITE to
 * register, edit or delete.
 */
#[Route('/communications')]
class CommunicationController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every recorded communication, newest first.
     */
    #[Route('', name: 'communication_index', methods: ['GET'])]
    public function index(CommunicationRepository $communications): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::COMMUNICATION);

        return $this->render('communication/index.html.twig', [
            'communications' => $communications->findAllOrdered(),
        ]);
    }

    /**
     * Registers a new communication.
     */
    #[Route('/new', name: 'communication_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::COMMUNICATION);

        return $this->handleForm(new Communication(), $request, $em);
    }

    /**
     * Edits an existing communication. The {@see Communication} is resolved from the {id} route
     * parameter by Symfony's entity value resolver.
     */
    #[Route('/{id}/edit', name: 'communication_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Communication $communication, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::COMMUNICATION);

        return $this->handleForm($communication, $request, $em);
    }

    /**
     * Deletes a communication. CSRF-protected POST; redirects back to the listing.
     */
    #[Route('/{id}/delete', name: 'communication_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Communication $communication, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::COMMUNICATION);

        if (!$this->isCsrfTokenValid('delete_communication'.(string) $communication->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $id = (string) $communication->getId();
        $subject = $communication->getSubject();

        $em->remove($communication);
        $em->flush();

        $this->auditLogger->log('communication.deleted', 'Communication', $id, $subject);
        $this->addFlash('success', sprintf('Comunicación «%s» eliminada.', $subject));

        return $this->redirectToRoute('communication_index');
    }

    /**
     * Builds and processes the communication form, persisting on a valid submission.
     */
    private function handleForm(Communication $communication, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $communication->getId();

        $form = $this->createForm(CommunicationType::class, $communication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($communication);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'communication.created' : 'communication.updated',
                'Communication',
                (string) $communication->getId(),
                $communication->getSubject(),
            );
            $this->addFlash('success', 'Comunicación guardada.');

            return $this->redirectToRoute('communication_index');
        }

        return $this->render('communication/form.html.twig', [
            'form' => $form,
            'communication' => $communication,
            'isNew' => $isNew,
        ]);
    }
}
