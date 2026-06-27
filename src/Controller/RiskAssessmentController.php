<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Entity\User;
use App\Enum\Area;
use App\Form\RiskAssessmentType;
use App\Security\Voter\AreaVoter;
use App\Security\Voter\RiskAssessmentVoter;
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
 * Adding/editing requires WRITE permission on Area::RISK_OPPORTUNITY; approving a revision is the
 * heavier, role-bound step gated by {@see RiskAssessmentVoter::APPROVE} (Dirección / the RSGMA).
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

        if ($assessment->getRiskOpportunity()->getId() !== $item->getId()) {
            throw $this->createNotFoundException('The assessment does not belong to the given item.');
        }

        return $this->handleForm($assessment, $item, $request, $em);
    }

    /**
     * Approves a valuation revision: records who signed it off and when (PC.03.0 §5.2). Restricted to
     * Dirección / the RSGMA. Idempotent guard: an already-approved revision is not re-stamped.
     */
    #[Route('/{assessmentId}/approve', name: 'risk_assessment_approve', requirements: ['assessmentId' => '\d+'], methods: ['POST'])]
    public function approve(
        #[MapEntity(id: 'id')] RiskOpportunity $item,
        #[MapEntity(id: 'assessmentId')] RiskAssessment $assessment,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(RiskAssessmentVoter::APPROVE, $assessment);

        if ($assessment->getRiskOpportunity()->getId() !== $item->getId()) {
            throw $this->createNotFoundException('The assessment does not belong to the given item.');
        }
        if (!$this->isCsrfTokenValid('risk_assessment_approve'.(string) $assessment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $redirect = $this->redirectToRoute('risk_show', ['id' => $item->getId()]);
        if (null !== $assessment->getApprovedBy()) {
            $this->addFlash('error', 'Esta revisión ya está aprobada.');

            return $redirect;
        }

        $approver = $this->getUser();
        if (!$approver instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $assessment->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable());
        $em->flush();
        // Audit AFTER the business flush, per the project convention.
        $this->auditLogger->log(
            'riskassessment.approved',
            'RiskAssessment',
            (string) $assessment->getId(),
            sprintf('%s · %s · Rev. %d', $item->getType()->label(), $assessment->getExercise(), $assessment->getRevisionNumber()),
        );
        $this->addFlash('success', 'Valoración aprobada.');

        return $redirect;
    }

    /**
     * Builds and processes the valuation form, computing the score and persisting on a valid
     * submission. Bumping the revision number on an approved valuation clears its approval: a new
     * revision is a fresh draft that Dirección must sign off again.
     */
    private function handleForm(RiskAssessment $assessment, RiskOpportunity $item, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $assessment->getId();
        $originalRevision = $assessment->getRevisionNumber();

        $form = $this->createForm(RiskAssessmentType::class, $assessment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$isNew && $assessment->getRevisionNumber() !== $originalRevision && null !== $assessment->getApprovedBy()) {
                $assessment->setApprovedBy(null)->setApprovedAt(null);
            }

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
