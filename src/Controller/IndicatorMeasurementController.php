<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Indicator;
use App\Entity\IndicatorMeasurement;
use App\Enum\Area;
use App\Form\IndicatorMeasurementType;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Periodic measurements (F.09.0 historical data) of an indicator: add and edit them. Listing
 * happens on the indicator detail page. A breached measurement can be escalated to a
 * non-conformity from there.
 *
 * Requires authentication and WRITE permission on Area::INDICATOR.
 */
#[Route('/indicators/{id}/measurements', requirements: ['id' => '\d+'])]
class IndicatorMeasurementController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Records a new measurement for the indicator, defaulting the year to the current one.
     */
    #[Route('/new', name: 'indicator_measurement_new', methods: ['GET', 'POST'])]
    public function new(Indicator $indicator, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::INDICATOR);

        $measurement = (new IndicatorMeasurement())
            ->setIndicator($indicator)
            ->setYear((int) date('Y'));

        return $this->handleForm($measurement, $indicator, $request, $em);
    }

    /**
     * Edits an existing measurement. Both entities are resolved by id; the measurement is checked
     * to belong to the indicator in the route.
     */
    #[Route('/{measurementId}/edit', name: 'indicator_measurement_edit', requirements: ['measurementId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(id: 'id')] Indicator $indicator,
        #[MapEntity(id: 'measurementId')] IndicatorMeasurement $measurement,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::INDICATOR);

        if ($measurement->getIndicator()->getId() !== $indicator->getId()) {
            throw $this->createNotFoundException('The measurement does not belong to the given indicator.');
        }

        return $this->handleForm($measurement, $indicator, $request, $em);
    }

    /**
     * Builds and processes the measurement form, persisting on a valid submission.
     */
    private function handleForm(IndicatorMeasurement $measurement, Indicator $indicator, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $measurement->getId();

        $form = $this->createForm(IndicatorMeasurementType::class, $measurement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($measurement);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'indicatormeasurement.created' : 'indicatormeasurement.updated',
                'IndicatorMeasurement',
                (string) $measurement->getId(),
                sprintf('%s · %02d/%d', $indicator->getName(), $measurement->getMonth(), $measurement->getYear()),
            );
            $this->addFlash('success', 'Medición de indicador guardada.');

            return $this->redirectToRoute('indicator_show', ['id' => $indicator->getId()]);
        }

        return $this->render('indicator_measurement/form.html.twig', [
            'form' => $form,
            'indicator' => $indicator,
            'measurement' => $measurement,
            'isNew' => $isNew,
        ]);
    }
}
