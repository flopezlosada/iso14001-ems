<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Supplier;
use App\Entity\SupplierEvaluation;
use App\Enum\Area;
use App\Form\SupplierEvaluationType;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Yearly evaluations (PC.05 §5.6) of a supplier: add and edit the entries that make up its
 * re-evaluation history. Listing happens on the supplier detail page.
 *
 * Requires authentication and WRITE permission on Area::SUPPLIER.
 */
#[Route('/suppliers/{id}/evaluations', requirements: ['id' => '\d+'])]
class SupplierEvaluationController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Adds a new yearly evaluation to the supplier, defaulting the year to the current one.
     */
    #[Route('/new', name: 'supplier_evaluation_new', methods: ['GET', 'POST'])]
    public function new(Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SUPPLIER);

        $evaluation = (new SupplierEvaluation())
            ->setSupplier($supplier)
            ->setYear((int) date('Y'));

        return $this->handleForm($evaluation, $supplier, $request, $em);
    }

    /**
     * Edits an existing evaluation. Both entities are resolved by id; the evaluation is checked
     * to belong to the supplier in the route.
     */
    #[Route('/{evaluationId}/edit', name: 'supplier_evaluation_edit', requirements: ['evaluationId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(id: 'id')] Supplier $supplier,
        #[MapEntity(id: 'evaluationId')] SupplierEvaluation $evaluation,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SUPPLIER);

        if ($evaluation->getSupplier()->getId() !== $supplier->getId()) {
            throw $this->createNotFoundException('The evaluation does not belong to the given supplier.');
        }

        return $this->handleForm($evaluation, $supplier, $request, $em);
    }

    /**
     * Builds and processes the evaluation form, persisting on a valid submission.
     */
    private function handleForm(SupplierEvaluation $evaluation, Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $evaluation->getId();

        $form = $this->createForm(SupplierEvaluationType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evaluation);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'supplierevaluation.created' : 'supplierevaluation.updated',
                'SupplierEvaluation',
                (string) $evaluation->getId(),
                sprintf('%s · %d', $supplier->getName(), $evaluation->getYear()),
            );
            $this->addFlash('success', 'Evaluación de proveedor guardada.');

            return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
        }

        return $this->render('supplier_evaluation/form.html.twig', [
            'form' => $form,
            'supplier' => $supplier,
            'evaluation' => $evaluation,
            'isNew' => $isNew,
        ]);
    }
}
