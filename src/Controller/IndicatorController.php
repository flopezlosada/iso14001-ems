<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Indicator;
use App\Enum\Area;
use App\Form\IndicatorType;
use App\Repository\IndicatorRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue of performance indicators (F.09.0) and their detail page, from which the periodic
 * measurements are managed.
 *
 * Requires authentication and per-area permission (Area::INDICATOR): READ to view, WRITE to manage.
 */
#[Route('/indicators')]
class IndicatorController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every indicator.
     */
    #[Route('', name: 'indicator_index', methods: ['GET'])]
    public function index(IndicatorRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::INDICATOR);

        return $this->render('indicator/index.html.twig', [
            'indicators' => $repository->findAllOrdered(),
        ]);
    }

    /**
     * Shows an indicator in detail, including its measurements.
     */
    #[Route('/{id}', name: 'indicator_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Indicator $indicator): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::INDICATOR);

        return $this->render('indicator/show.html.twig', [
            'indicator' => $indicator,
        ]);
    }

    /**
     * Registers a new indicator.
     */
    #[Route('/new', name: 'indicator_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::INDICATOR);

        return $this->handleForm(new Indicator(), $request, $em);
    }

    /**
     * Edits an existing indicator.
     */
    #[Route('/{id}/edit', name: 'indicator_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Indicator $indicator, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::INDICATOR);

        return $this->handleForm($indicator, $request, $em);
    }

    /**
     * Builds and processes the indicator form, persisting on a valid submission.
     */
    private function handleForm(Indicator $indicator, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $indicator->getId();

        $form = $this->createForm(IndicatorType::class, $indicator);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($indicator);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'indicator.created' : 'indicator.updated',
                'Indicator',
                (string) $indicator->getId(),
                $indicator->getName(),
            );
            $this->addFlash('success', 'Indicador guardado.');

            return $this->redirectToRoute('indicator_show', ['id' => $indicator->getId()]);
        }

        return $this->render('indicator/form.html.twig', [
            'form' => $form,
            'indicator' => $indicator,
            'isNew' => $isNew,
        ]);
    }
}
