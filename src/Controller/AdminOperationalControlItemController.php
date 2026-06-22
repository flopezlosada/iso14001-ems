<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OperationalControlItem;
use App\Form\OperationalControlItemType;
use App\Repository\OperationalControlItemRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin management of the operational-control checklist catalogue (PG-08.01): list, add and edit the
 * items the monthly inspection is built from. Items are deactivated, never deleted, so past answers
 * keep their reference. The whole /admin area is restricted to ROLE_ADMIN in security.yaml.
 */
#[Route('/admin/checklist-control-operacional')]
#[IsGranted('ROLE_ADMIN')]
class AdminOperationalControlItemController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every catalogue item (active and inactive), grouped-ready by section and position.
     */
    #[Route('', name: 'admin_operational_control_item_index', methods: ['GET'])]
    public function index(OperationalControlItemRepository $items): Response
    {
        return $this->render('admin/operational_control_item/index.html.twig', [
            'items' => $items->findAllOrdered(),
        ]);
    }

    /**
     * Creates a new checklist item.
     */
    #[Route('/nuevo', name: 'admin_operational_control_item_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm(new OperationalControlItem(), $request, $em);
    }

    /**
     * Edits an existing checklist item (including activating/deactivating it).
     */
    #[Route('/{id}/editar', name: 'admin_operational_control_item_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(OperationalControlItem $item, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm($item, $request, $em);
    }

    /**
     * Builds and processes the item form, persisting on a valid submission.
     */
    private function handleForm(OperationalControlItem $item, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $item->getId();
        $form = $this->createForm(OperationalControlItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'opcontrol_item.created' : 'opcontrol_item.updated',
                'OperationalControlItem',
                (string) $item->getId(),
                sprintf('%s · %s', $item->getSection()->label(), $item->getLabel()),
            );
            $this->addFlash('success', 'Ítem del checklist guardado.');

            return $this->redirectToRoute('admin_operational_control_item_index');
        }

        return $this->render('admin/operational_control_item/form.html.twig', [
            'form' => $form,
            'item' => $item,
            'isNew' => $isNew,
        ]);
    }
}
