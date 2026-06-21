<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Enum\Area;
use App\Form\RiskAssessmentType;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use App\Service\RiskScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Yearly valuations (PC.03.0 §5.2) of a risk/opportunity: add and edit them together with their
 * action plan. The score and category are (re)computed on every save by {@see RiskScoreCalculator}.
 * Listing happens on the item detail page.
 *
 * Requires authentication and WRITE permission on Area::RISK_OPPORTUNITY.
 */
#[Route('/risks/{id}/assessments', requirements: ['id' => '\d+'])]
class RiskAssessmentController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RiskScoreCalculator $calculator,
    ) {
    }

    /**
     * Adds a new valuation to the item.
     */
    #[Route('/new', name: 'risk_assessment_new', methods: ['GET', 'POST'])]
    public function new(RiskOpportunity $item, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::RISK_OPPORTUNITY);

        $assessment = new RiskAssessment();
        $item->addAssessment($assessment);

        return $this->handleForm($assessment, $item, $request, $em);
    }

    /**
     * Edits an existing valuation. Both entities are resolved by id; the valuation is checked to
     * belong to the item in the route.
     */
    #[Route('/{assessmentId}/edit', name: 'risk_assessment_edit', requirements: ['assessmentId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(id: 'id')] RiskOpportunity $item,
        #[MapEntity(id: 'assessmentId')] RiskAssessment $assessment,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::RISK_OPPORTUNITY);

        if ($assessment->getRiskOpportunity()?->getId() !== $item->getId()) {
            throw $this->createNotFoundException('The assessment does not belong to the given item.');
        }

        return $this->handleForm($assessment, $item, $request, $em);
    }

    /**
     * Builds and processes the valuation form, computing the score and persisting on a valid
     * submission.
     */
    private function handleForm(RiskAssessment $assessment, RiskOpportunity $item, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $assessment->getId();

        $form = $this->createForm(RiskAssessmentType::class, $assessment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->calculator->apply($assessment);

            $em->persist($assessment);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'riskassessment.created' : 'riskassessment.updated',
                'RiskAssessment',
                (string) $assessment->getId(),
                sprintf('%s · %s · %s', $item->getType()->label(), $assessment->getExercise(), $assessment->getCategory()?->label($item->getType()) ?? ''),
            );
            $this->addFlash('success', 'Valoración guardada.');

            return $this->redirectToRoute('risk_show', ['id' => $item->getId()]);
        }

        return $this->render('risk_assessment/form.html.twig', [
            'form' => $form,
            'item' => $item,
            'assessment' => $assessment,
            'isNew' => $isNew,
        ]);
    }
}
