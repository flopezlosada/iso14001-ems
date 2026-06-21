<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ProcessArea;
use App\Enum\Area;
use App\Form\ProcessAreaType;
use App\Repository\ProcessAreaRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Configurable catalogue of process/functional areas used by risks and opportunities. Areas are
 * created, renamed and (de)activated here; they are never deleted, so historical risks keep their
 * area for traceability (clause 7.5).
 *
 * Requires authentication and per-area permission (Area::RISK_OPPORTUNITY): READ to view, WRITE to
 * manage.
 */
#[Route('/risks/areas')]
class ProcessAreaController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every area with its active state.
     */
    #[Route('', name: 'risk_area_index', methods: ['GET'])]
    public function index(ProcessAreaRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::RISK_OPPORTUNITY);

        return $this->render('process_area/index.html.twig', [
            'areas' => $repository->findAllOrdered(),
        ]);
    }

    /**
     * Creates a new area.
     */
    #[Route('/new', name: 'risk_area_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::RISK_OPPORTUNITY);

        return $this->handleForm(new ProcessArea(), $request, $em);
    }

    /**
     * Renames or (de)activates an existing area.
     */
    #[Route('/{id}/edit', name: 'risk_area_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(ProcessArea $area, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::RISK_OPPORTUNITY);

        return $this->handleForm($area, $request, $em);
    }

    /**
     * Builds and processes the area form, persisting on a valid submission.
     */
    private function handleForm(ProcessArea $area, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $area->getId();

        $form = $this->createForm(ProcessAreaType::class, $area);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($area);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'processarea.created' : 'processarea.updated',
                'ProcessArea',
                (string) $area->getId(),
                $area->getName(),
            );
            $this->addFlash('success', 'Área guardada.');

            return $this->redirectToRoute('risk_area_index');
        }

        return $this->render('process_area/form.html.twig', [
            'form' => $form,
            'area' => $area,
            'isNew' => $isNew,
        ]);
    }
}
