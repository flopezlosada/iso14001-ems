<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EnvironmentalAspect;
use App\Enum\Area;
use App\Enum\AspectType;
use App\Form\EnvironmentalAspectType;
use App\Repository\AuditLogRepository;
use App\Repository\EnvironmentalAspectRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AspectWorkflowStatusProvider;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue of direct environmental aspects (PG-06.01 / RG-06.01.01) and their detail page, from
 * which the yearly evaluations are managed.
 *
 * Requires authentication and per-area permission (Area::ASPECT): READ to view, WRITE to manage.
 */
#[Route('/aspects')]
class EnvironmentalAspectController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every aspect with its status for the current year, above a "qué falta este curso" guide
     * of the pending work. Evaluations are eager-loaded to read each aspect's status without an N+1.
     */
    #[Route('', name: 'aspect_index', methods: ['GET'])]
    public function index(EnvironmentalAspectRepository $repository, AspectWorkflowStatusProvider $workflow): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::ASPECT);

        $currentYear = (int) date('Y');

        return $this->render('environmental_aspect/index.html.twig', [
            'aspects' => $repository->findAllWithEvaluations(),
            'currentYear' => $currentYear,
            // Guía "qué falta este curso": pendientes calculados, cada uno enlazado a su acción.
            'status' => $workflow->for($currentYear),
        ]);
    }

    /**
     * Shows an aspect in detail, including its yearly evaluations.
     */
    #[Route('/{id}', name: 'aspect_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(EnvironmentalAspect $aspect, AuditLogRepository $auditLogs): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::ASPECT);

        return $this->render('environmental_aspect/show.html.twig', [
            'aspect' => $aspect,
            'activity' => $auditLogs->findForSubject('EnvironmentalAspect', (string) $aspect->getId()),
        ]);
    }

    /**
     * Registers a new direct aspect.
     */
    #[Route('/new', name: 'aspect_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::ASPECT);

        // This module covers direct aspects; type is fixed here (other types arrive later).
        $aspect = (new EnvironmentalAspect())->setType(AspectType::DIRECT);

        return $this->handleForm($aspect, $request, $em);
    }

    /**
     * Edits an existing aspect.
     */
    #[Route('/{id}/edit', name: 'aspect_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(EnvironmentalAspect $aspect, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::ASPECT);

        return $this->handleForm($aspect, $request, $em);
    }

    /**
     * Builds and processes the aspect form, persisting on a valid submission.
     */
    private function handleForm(EnvironmentalAspect $aspect, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $aspect->getId();

        $form = $this->createForm(EnvironmentalAspectType::class, $aspect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($aspect);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'aspect.created' : 'aspect.updated',
                'EnvironmentalAspect',
                (string) $aspect->getId(),
                $aspect->getName(),
            );
            $this->addFlash('success', 'Aspecto ambiental guardado.');

            return $this->redirectToRoute('aspect_show', ['id' => $aspect->getId()]);
        }

        return $this->render('environmental_aspect/form.html.twig', [
            'form' => $form,
            'aspect' => $aspect,
            'isNew' => $isNew,
        ]);
    }
}
