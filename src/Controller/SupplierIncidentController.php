<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Supplier;
use App\Entity\SupplierIncident;
use App\Enum\Area;
use App\Form\SupplierIncidentType;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Incidents (PC.05 §5.6) of a supplier: add and edit them. Listing happens on the supplier
 * detail page. Severe incidents can be escalated to a non-conformity from there.
 *
 * Requires authentication and WRITE permission on Area::SUPPLIER.
 */
#[Route('/suppliers/{id}/incidents', requirements: ['id' => '\d+'])]
class SupplierIncidentController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Records a new incident for the supplier.
     */
    #[Route('/new', name: 'supplier_incident_new', methods: ['GET', 'POST'])]
    public function new(Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SUPPLIER);

        $incident = (new SupplierIncident())->setSupplier($supplier);

        return $this->handleForm($incident, $supplier, $request, $em);
    }

    /**
     * Edits an existing incident. Both entities are resolved by id; the incident is checked to
     * belong to the supplier in the route.
     */
    #[Route('/{incidentId}/edit', name: 'supplier_incident_edit', requirements: ['incidentId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(id: 'id')] Supplier $supplier,
        #[MapEntity(id: 'incidentId')] SupplierIncident $incident,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SUPPLIER);

        if ($incident->getSupplier()->getId() !== $supplier->getId()) {
            throw $this->createNotFoundException('The incident does not belong to the given supplier.');
        }

        return $this->handleForm($incident, $supplier, $request, $em);
    }

    /**
     * Builds and processes the incident form, persisting on a valid submission.
     */
    private function handleForm(SupplierIncident $incident, Supplier $supplier, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $incident->getId();

        $form = $this->createForm(SupplierIncidentType::class, $incident);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($incident);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'supplierincident.created' : 'supplierincident.updated',
                'SupplierIncident',
                (string) $incident->getId(),
                sprintf('%s · %s', $supplier->getName(), $incident->getOccurredOn()->format('d/m/Y')),
            );
            $this->addFlash('success', 'Incidencia de proveedor guardada.');

            return $this->redirectToRoute('supplier_show', ['id' => $supplier->getId()]);
        }

        return $this->render('supplier_incident/form.html.twig', [
            'form' => $form,
            'supplier' => $supplier,
            'incident' => $incident,
            'isNew' => $isNew,
        ]);
    }
}
