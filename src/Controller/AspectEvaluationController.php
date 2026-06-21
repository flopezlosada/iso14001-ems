<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AspectEvaluation;
use App\Entity\EnvironmentalAspect;
use App\Enum\Area;
use App\Form\AspectEvaluationType;
use App\Security\Voter\AreaVoter;
use App\Service\AspectSignificanceCalculator;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Yearly evaluations (PG-06.01 Anexo I) of an aspect: add and edit them. The significance is
 * (re)computed on every save by {@see AspectSignificanceCalculator}. Listing happens on the
 * aspect detail page.
 *
 * Requires authentication and WRITE permission on Area::ASPECT.
 */
#[Route('/aspects/{id}/evaluations', requirements: ['id' => '\d+'])]
class AspectEvaluationController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AspectSignificanceCalculator $calculator,
    ) {
    }

    /**
     * Adds a new yearly evaluation to the aspect, defaulting the year to the current one.
     */
    #[Route('/new', name: 'aspect_evaluation_new', methods: ['GET', 'POST'])]
    public function new(EnvironmentalAspect $aspect, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::ASPECT);

        $evaluation = (new AspectEvaluation())
            ->setAspect($aspect)
            ->setYear((int) date('Y'));

        return $this->handleForm($evaluation, $aspect, $request, $em);
    }

    /**
     * Edits an existing evaluation. Both entities are resolved by id; the evaluation is checked to
     * belong to the aspect in the route.
     */
    #[Route('/{evaluationId}/edit', name: 'aspect_evaluation_edit', requirements: ['evaluationId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(id: 'id')] EnvironmentalAspect $aspect,
        #[MapEntity(id: 'evaluationId')] AspectEvaluation $evaluation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::ASPECT);

        if ($evaluation->getAspect()->getId() !== $aspect->getId()) {
            throw $this->createNotFoundException('The evaluation does not belong to the given aspect.');
        }

        return $this->handleForm($evaluation, $aspect, $request, $em);
    }

    /**
     * Builds and processes the evaluation form, computing significance and persisting on a valid
     * submission.
     */
    private function handleForm(AspectEvaluation $evaluation, EnvironmentalAspect $aspect, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $evaluation->getId();

        $form = $this->createForm(AspectEvaluationType::class, $evaluation, [
            'aspect_type' => $aspect->getType(),
            'category' => $aspect->getCategory(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->calculator->apply($evaluation);

            $em->persist($evaluation);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'aspectevaluation.created' : 'aspectevaluation.updated',
                'AspectEvaluation',
                (string) $evaluation->getId(),
                sprintf('%s · %d · %s', $aspect->getName(), $evaluation->getYear(), $evaluation->isSignificant() ? 'significativo' : 'no significativo'),
            );
            $this->addFlash('success', 'Evaluación de aspecto guardada.');

            return $this->redirectToRoute('aspect_show', ['id' => $aspect->getId()]);
        }

        return $this->render('aspect_evaluation/form.html.twig', [
            'form' => $form,
            'aspect' => $aspect,
            'evaluation' => $evaluation,
            'isNew' => $isNew,
        ]);
    }
}
