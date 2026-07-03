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
use App\Util\SchoolYear;
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

        // Offer the previous/current/next school year, minus the ones this item is already valued for:
        // a valuation is unique per course, so offering an already-valued year would only dead-end on
        // the unique-constraint error.
        $current = SchoolYear::current(new \DateTimeImmutable('today'));
        $valued = $this->valuedExercises($item);
        $available = array_values(array_filter(
            [SchoolYear::previous($current), $current, SchoolYear::next($current)],
            static fn (string $year): bool => !isset($valued[$year]),
        ));

        if ([] === $available) {
            $this->addFlash('error', 'Este riesgo/oportunidad ya tiene valoración para los cursos disponibles. Edita la existente para corregirla.');

            return $this->redirectToRoute('risk_show', ['id' => $item->getId()]);
        }

        $assessment = new RiskAssessment();
        // Land the selector on the current year when it is still free; otherwise on the first available
        // one, so the preselected value is always a valid choice.
        $assessment->setExercise(\in_array($current, $available, true) ? $current : $available[0]);
        $item->addAssessment($assessment);

        return $this->handleForm($assessment, $item, $request, $em, $available, lockExercise: false);
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

        // The course is immutable on edit (a valuation never moves to another year), so the only
        // choice is the one it already belongs to, and the selector is locked.
        return $this->handleForm($assessment, $item, $request, $em, [$assessment->getExercise()], lockExercise: true);
    }

    /**
     * Detail of a single valuation (a risk/opportunity for one course): its factors, computed score
     * and category, justification and action plan, with the approval state. This is the page the
     * cockpit and inventory link to when you go to review or approve the current course — the yearly
     * history of the item is a secondary view, reached from here via "ver el histórico".
     */
    #[Route('/{assessmentId}', name: 'risk_assessment_show', requirements: ['assessmentId' => '\d+'], methods: ['GET'])]
    public function show(
        #[MapEntity(id: 'id')] RiskOpportunity $item,
        #[MapEntity(id: 'assessmentId')] RiskAssessment $assessment,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::RISK_OPPORTUNITY);

        if ($assessment->getRiskOpportunity()->getId() !== $item->getId()) {
            throw $this->createNotFoundException('The assessment does not belong to the given item.');
        }

        return $this->render('risk_assessment/show.html.twig', [
            'item' => $item,
            'assessment' => $assessment,
        ]);
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
     *
     * @param list<string> $exerciseChoices the school years offered in the "Curso" selector
     * @param bool          $lockExercise   whether the "Curso" selector is locked (true on edit)
     */
    private function handleForm(RiskAssessment $assessment, RiskOpportunity $item, Request $request, EntityManagerInterface $em, array $exerciseChoices, bool $lockExercise): Response
    {
        $isNew = null === $assessment->getId();
        $wasApproved = null !== $assessment->getApprovedBy();
        // Snapshot the approved content before binding, to tell a real change from a no-op save.
        $signatureBefore = $wasApproved ? $this->contentSignature($assessment) : null;

        $form = $this->createForm(RiskAssessmentType::class, $assessment, [
            'exercise_choices' => $exerciseChoices,
            'lock_exercise' => $lockExercise,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A real edit of an already-approved valuation is a new revision: bump the number and send
            // it back to draft (pending) so Dirección approves the change again. A no-op save (nothing
            // actually changed) must NOT touch the revision or the approval.
            if ($wasApproved && $signatureBefore !== $this->contentSignature($assessment)) {
                $assessment->setRevisionNumber($assessment->getRevisionNumber() + 1);
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

    /**
     * A stable fingerprint of the valuation's meaningful content (scoring factors, justification and
     * the action plan), used to tell a real edit from a no-op save. The score/category are excluded
     * (they are derived) and so are approval and revision (they are the consequence, not the content).
     * Nulls and empty strings are normalised so "" and null compare equal.
     *
     * @param RiskAssessment $assessment the valuation to fingerprint
     *
     * @return string the content fingerprint
     */
    private function contentSignature(RiskAssessment $assessment): string
    {
        $parts = [
            (string) $assessment->getProbability()->value,
            (string) $assessment->getImpact()->value,
            (string) $assessment->getJustification(),
        ];
        foreach ($assessment->getActions() as $action) {
            $parts[] = [
                (string) $action->getDescription(),
                (string) $action->getResponsible()?->getId(),
                (string) $action->getDeadline(),
                (string) $action->getEfficacy(),
                $action->getEvaluatedAt()?->format('Y-m-d') ?? '',
            ];
        }

        // JSON (not a delimiter join) so a newline in the justification or a "|" in a field cannot
        // forge a collision between two different contents. Actions are ordered (RiskAssessment's
        // OrderBy), so the fingerprint is stable across reloads.
        return json_encode($parts, JSON_THROW_ON_ERROR);
    }

    /**
     * The school years the item is already valued for, as a set keyed by year ("YYYY-YYYY" => true)
     * for O(1) lookups. Only persisted valuations count, so a not-yet-saved draft does not exclude
     * its own year.
     *
     * @param RiskOpportunity $item the risk/opportunity whose valuations to inspect
     *
     * @return array<string, true> the already-valued school years as a lookup set
     */
    private function valuedExercises(RiskOpportunity $item): array
    {
        $valued = [];
        foreach ($item->getAssessments() as $assessment) {
            if (null !== $assessment->getId()) {
                $valued[$assessment->getExercise()] = true;
            }
        }

        return $valued;
    }
}
