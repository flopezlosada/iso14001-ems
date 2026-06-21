<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmergencyDrill;
use App\Enum\Area;
use App\Form\EmergencyDrillType;
use App\Repository\EmergencyDrillRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Emergency drill reports (record RG-08.02.01 of procedure PG-08.02): chronological list of drills
 * carried out and add/edit one.
 *
 * Requires authentication and per-area permission (Area::EMERGENCY): READ to view, WRITE to change.
 */
#[Route('/emergency-drills')]
class EmergencyDrillController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists the emergency drills, newest first.
     */
    #[Route('', name: 'emergency_drill_index', methods: ['GET'])]
    public function index(EmergencyDrillRepository $drills): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::EMERGENCY);

        return $this->render('emergency_drill/index.html.twig', [
            'drills' => $drills->findRecent(),
        ]);
    }

    /**
     * Creates a new emergency drill report.
     */
    #[Route('/new', name: 'emergency_drill_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::EMERGENCY);

        return $this->handleForm(new EmergencyDrill(), $request, $em);
    }

    /**
     * Edits an existing emergency drill report. The {@see EmergencyDrill} is resolved from the {id}
     * route parameter by Symfony's entity value resolver.
     */
    #[Route('/{id}/edit', name: 'emergency_drill_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(EmergencyDrill $drill, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::EMERGENCY);

        return $this->handleForm($drill, $request, $em);
    }

    /**
     * Builds and processes the drill report form, persisting on a valid submission.
     */
    private function handleForm(EmergencyDrill $drill, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EmergencyDrillType::class, $drill);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = null === $drill->getId();
            $em->persist($drill);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'emergency_drill.created' : 'emergency_drill.updated',
                'EmergencyDrill',
                (string) $drill->getId(),
                sprintf('Simulacro %s · %s', $drill->getEmergencyType(), $drill->getDrillDate()->format('d/m/Y')),
            );
            $this->addFlash('success', 'Informe de simulacro guardado.');

            return $this->redirectToRoute('emergency_drill_index');
        }

        return $this->render('emergency_drill/form.html.twig', [
            'form' => $form,
            'drill' => $drill,
        ]);
    }
}
