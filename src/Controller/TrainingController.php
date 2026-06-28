<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TrainingAction;
use App\Enum\Area;
use App\Form\TrainingActionType;
use App\Repository\TrainingActionRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annual training plan (form F.03.0): list a year's training actions and add/edit one.
 *
 * Requires authentication and per-area permission (Area::TRAINING): READ to view, WRITE to
 * register or edit.
 */
#[Route('/training')]
class TrainingController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Redirects to the current year's training plan.
     */
    #[Route('', name: 'training_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::TRAINING);

        return $this->redirectToRoute('training_year', ['year' => (int) date('Y')]);
    }

    /**
     * Lists every training action planned for the given year.
     */
    #[Route('/{year}', name: 'training_year', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function year(int $year, TrainingActionRepository $actions): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::TRAINING);

        return $this->render('training/index.html.twig', [
            'year' => $year,
            'actions' => $actions->findForYear($year),
        ]);
    }

    /**
     * Creates a new training action for the given year.
     */
    #[Route('/{year}/new', name: 'training_new', requirements: ['year' => '\d{4}'], methods: ['GET', 'POST'])]
    public function new(int $year, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::TRAINING);

        $action = (new TrainingAction())->setPlanYear($year);

        return $this->handleForm($action, $year, $request, $em);
    }

    /**
     * Edits an existing training action. The {@see TrainingAction} is resolved from the {id} route
     * parameter by Symfony's entity value resolver.
     */
    #[Route('/{year}/{id}/edit', name: 'training_edit', requirements: ['year' => '\d{4}', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $year, TrainingAction $action, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::TRAINING);

        if ($action->getPlanYear() !== $year) {
            throw $this->createNotFoundException('The training action does not belong to the given year.');
        }

        return $this->handleForm($action, $year, $request, $em);
    }

    /**
     * Builds and processes the training action form, persisting on a valid submission.
     */
    private function handleForm(TrainingAction $action, int $year, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TrainingActionType::class, $action);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = null === $action->getId();
            $em->persist($action);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'training.created' : 'training.updated',
                'TrainingAction',
                (string) $action->getId(),
                sprintf('Formación %s · %s', $action->getType()?->label() ?? 'sin clasificar', $action->getDescription()),
            );
            $this->addFlash('success', 'Acción formativa guardada.');

            return $this->redirectToRoute('training_year', ['year' => $year]);
        }

        return $this->render('training/form.html.twig', [
            'form' => $form,
            'year' => $year,
            'action' => $action,
        ]);
    }
}
