<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Enum\Area;
use App\Form\RiskOpportunityType;
use App\Repository\AuditLogRepository;
use App\Repository\RiskAssessmentRepository;
use App\Repository\RiskOpportunityRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use App\Service\RiskScoreCalculator;
use App\Service\RiskWorkflowStatusProvider;
use App\Util\SchoolYear;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalogue of risks and opportunities (PC.03.0 / F.08.0) and their detail page, from which the
 * yearly valuations are managed.
 *
 * Requires authentication and per-area permission (Area::RISK_OPPORTUNITY): READ to view, WRITE to
 * manage.
 */
#[Route('/risks')]
class RiskOpportunityController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every risk and opportunity with its area. Surfaces the current course and the previous one
     * so the template can offer cloning the previous course's valuations into the current one.
     */
    #[Route('', name: 'risk_index', methods: ['GET'])]
    public function index(RiskOpportunityRepository $repository, RiskAssessmentRepository $assessments, RiskWorkflowStatusProvider $workflow): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::RISK_OPPORTUNITY);

        $currentExercise = SchoolYear::current(new \DateTimeImmutable());
        $previousExercise = SchoolYear::previous($currentExercise);

        return $this->render('risk_opportunity/index.html.twig', [
            'items' => $repository->findAllOrdered(),
            'currentExercise' => $currentExercise,
            'previousExercise' => $previousExercise,
            // Only offer cloning when the previous course actually has something to bring over.
            'previousExerciseCount' => \count($assessments->findValuedRiskOpportunityIds($previousExercise)),
            // Guía "qué falta este curso": pendientes calculados, cada uno enlazado a su acción.
            'status' => $workflow->for($currentExercise),
        ]);
    }

    /**
     * Clones the previous course's valuations into the given (current) course as editable drafts: for
     * every risk/opportunity valued in the previous course but not yet in the target one, it copies
     * the valuation (scoring factors, justification and action plan) as an unapproved Rev. 01 and
     * recomputes its score. Risks already valued for the target course are skipped, so it never
     * overwrites and is safe to run with the course empty or half-filled. CSRF-protected POST.
     *
     * The target course is explicit in the route (deterministic, like the interested-parties register)
     * rather than derived from the latest valuation, so a half-prepared course is filled in place
     * instead of jumping a year ahead.
     */
    #[Route('/clone-assessments/{exercise}', name: 'risk_clone_assessments', requirements: ['exercise' => '\d{4}-\d{4}'], methods: ['POST'])]
    public function cloneAssessmentsFromPreviousExercise(
        string $exercise,
        Request $request,
        EntityManagerInterface $em,
        RiskAssessmentRepository $assessments,
        RiskScoreCalculator $calculator,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::RISK_OPPORTUNITY);

        if (!$this->isCsrfTokenValid('risk_clone_assessments'.$exercise, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $sourceExercise = SchoolYear::previous($exercise);
        $alreadyValued = array_fill_keys($assessments->findValuedRiskOpportunityIds($exercise), true);

        $toClone = array_filter(
            $assessments->findByExercise($sourceExercise),
            static fn (RiskAssessment $assessment): bool => !isset($alreadyValued[$assessment->getRiskOpportunity()->getId()]),
        );

        foreach ($toClone as $assessment) {
            $copy = $assessment->copyForExercise($exercise);
            $calculator->apply($copy);
            $em->persist($copy);
        }
        $em->flush();

        $cloned = \count($toClone);
        if ($cloned > 0) {
            $this->auditLogger->log(
                'riskassessment.cloned_from_previous',
                'RiskAssessment',
                $exercise,
                sprintf('%d valoraciones clonadas de %s a %s', $cloned, $sourceExercise, $exercise),
            );
            $this->addFlash('success', sprintf('Se han creado %d borradores de valoración para el curso %s a partir de %s.', $cloned, $exercise, $sourceExercise));
        } else {
            $this->addFlash('success', sprintf('No había valoraciones nuevas que clonar al curso %s.', $exercise));
        }

        return $this->redirectToRoute('risk_index');
    }

    /**
     * Shows a risk/opportunity in detail, including its yearly valuations.
     */
    #[Route('/{id}', name: 'risk_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, RiskOpportunityRepository $repository, AuditLogRepository $auditLogs): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::RISK_OPPORTUNITY);

        $item = $repository->findWithAssessmentsAndActions($id);
        if (null === $item) {
            throw $this->createNotFoundException();
        }

        return $this->render('risk_opportunity/show.html.twig', [
            'item' => $item,
            'activity' => $auditLogs->findForSubject('RiskOpportunity', (string) $item->getId()),
        ]);
    }

    /**
     * Registers a new risk or opportunity.
     */
    #[Route('/new', name: 'risk_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::RISK_OPPORTUNITY);

        return $this->handleForm(new RiskOpportunity(), $request, $em);
    }

    /**
     * Edits an existing risk or opportunity.
     */
    #[Route('/{id}/edit', name: 'risk_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(RiskOpportunity $item, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::RISK_OPPORTUNITY);

        return $this->handleForm($item, $request, $em);
    }

    /**
     * Builds and processes the item form, persisting on a valid submission.
     */
    private function handleForm(RiskOpportunity $item, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $item->getId();

        $form = $this->createForm(RiskOpportunityType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'riskopportunity.created' : 'riskopportunity.updated',
                'RiskOpportunity',
                (string) $item->getId(),
                sprintf('%s · %s', $item->getType()->label(), $item->getProcessArea()->getName()),
            );
            $this->addFlash('success', 'Riesgo/oportunidad guardado.');

            return $this->redirectToRoute('risk_show', ['id' => $item->getId()]);
        }

        return $this->render('risk_opportunity/form.html.twig', [
            'form' => $form,
            'item' => $item,
            'isNew' => $isNew,
        ]);
    }
}
