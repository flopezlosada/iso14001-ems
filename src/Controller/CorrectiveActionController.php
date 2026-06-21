<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CorrectiveAction;
use App\Entity\NonConformity;
use App\Enum\Area;
use App\Form\CorrectiveActionType;
use App\Repository\CorrectiveActionRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Corrective actions (PC.10.0 §4.3) of a non-conformity: add and edit the entries of its action
 * plan (PAC). Listing happens on the non-conformity detail page.
 *
 * Requires authentication and WRITE permission on Area::NONCONFORMITY.
 */
#[Route('/non-conformities/{id}/actions', requirements: ['id' => '\d+'])]
class CorrectiveActionController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Adds a new corrective action to the non-conformity, assigning its sequence on save.
     */
    #[Route('/new', name: 'corrective_action_new', methods: ['GET', 'POST'])]
    public function new(NonConformity $nonConformity, Request $request, EntityManagerInterface $em, CorrectiveActionRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::NONCONFORMITY);

        $action = (new CorrectiveAction())->setNonConformity($nonConformity);

        return $this->handleForm($action, $nonConformity, $request, $em, $repository);
    }

    /**
     * Edits an existing corrective action. Both entities are resolved by id; the action is
     * checked to belong to the non-conformity in the route.
     */
    #[Route('/{actionId}/edit', name: 'corrective_action_edit', requirements: ['actionId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(id: 'id')] NonConformity $nonConformity,
        #[MapEntity(id: 'actionId')] CorrectiveAction $action,
        Request $request,
        EntityManagerInterface $em,
        CorrectiveActionRepository $repository,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::NONCONFORMITY);

        if ($action->getNonConformity()->getId() !== $nonConformity->getId()) {
            throw $this->createNotFoundException('The corrective action does not belong to the given non-conformity.');
        }

        return $this->handleForm($action, $nonConformity, $request, $em, $repository);
    }

    /**
     * Builds and processes the corrective action form, persisting on a valid submission. On
     * creation the sequence is assigned; the authorisation date is kept in sync with the
     * authoriser.
     */
    private function handleForm(
        CorrectiveAction $action,
        NonConformity $nonConformity,
        Request $request,
        EntityManagerInterface $em,
        CorrectiveActionRepository $repository,
    ): Response {
        $isNew = null === $action->getId();

        $form = $this->createForm(CorrectiveActionType::class, $action);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $action->setSequence($repository->nextSequence($nonConformity));
            }
            $this->syncAuthorizedAt($action);

            $em->persist($action);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'correctiveaction.created' : 'correctiveaction.updated',
                'CorrectiveAction',
                (string) $action->getId(),
                sprintf('%s / %s', $nonConformity->getReference(), $action->getReference()),
            );
            $this->addFlash('success', sprintf('Acción correctiva %s guardada.', $action->getReference()));

            return $this->redirectToRoute('non_conformity_show', ['id' => $nonConformity->getId()]);
        }

        return $this->render('corrective_action/form.html.twig', [
            'form' => $form,
            'nonConformity' => $nonConformity,
            'action' => $action,
            'isNew' => $isNew,
        ]);
    }

    /**
     * Keeps the authorisation date consistent with the authoriser: stamped when an authoriser is
     * set (if not already), cleared when there is none.
     */
    private function syncAuthorizedAt(CorrectiveAction $action): void
    {
        if (null !== $action->getAuthorizedBy()) {
            if (null === $action->getAuthorizedAt()) {
                $action->setAuthorizedAt(new \DateTimeImmutable());
            }

            return;
        }

        $action->setAuthorizedAt(null);
    }
}
