<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Supplier;
use App\Enum\Area;
use App\Form\SupplierType;
use App\Repository\AuditLogRepository;
use App\Repository\SupplierRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Supplier control list (F.12.0) and the supplier detail/register form (PC.05).
 *
 * Requires authentication and per-area permission (Area::SUPPLIER): READ to view, WRITE to
 * register or edit. Yearly evaluations are managed from the detail page.
 */
#[Route('/suppliers')]
class SupplierController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every supplier (the F.12.0 control list).
     */
    #[Route('', name: 'supplier_index', methods: ['GET'])]
    public function index(SupplierRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SUPPLIER);

        return $this->render('supplier/index.html.twig', [
            // Eager-fetch evaluations so evaluationFor() does not trigger an N+1 across the list.
            'suppliers' => $repository->findAllWithEvaluations(),
            'currentYear' => (int) date('Y'),
        ]);
    }

    /**
     * Shows a supplier in detail, including its yearly evaluations.
     */
    #[Route('/{id}', name: 'supplier_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Supplier $supplier, AuditLogRepository $auditLogs): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SUPPLIER);

        return $this->render('supplier/show.html.twig', [
            'supplier' => $supplier,
            'currentYear' => (int) date('Y'),
            'activity' => $auditLogs->findForSubject('Supplier', (string) $supplier->getId()),
        ]);
    }

    /**
     * Registers a new supplier.
     */
    #[Route('/new', name: 'supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SUPPLIER);

        return $this->handleForm(new Supplier(), $request, $em);
    }

    /**
     * Edits an existing supplier.
     */
    #[Route('/{id}/edit', name: 'supplier_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SUPPLIER);

        return $this->handleForm($supplier, $request, $em);
    }

    /**
     * Builds and processes the supplier form, persisting on a valid submission.
     */
    private function handleForm(Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $supplier->getId();

        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($supplier);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'supplier.created' : 'supplier.updated',
                'Supplier',
                (string) $supplier->getId(),
                $supplier->getName(),
            );
            $this->addFlash('success', 'Proveedor guardado.');

            return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
        }

        return $this->render('supplier/form.html.twig', [
            'form' => $form,
            'supplier' => $supplier,
            'isNew' => $isNew,
        ]);
    }
}
