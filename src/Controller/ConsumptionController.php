<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConsumptionReading;
use App\Form\ConsumptionReadingType;
use App\Repository\ConsumptionReadingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Monthly consumption capture (form F-6.1.2): list a year's readings and add/edit one.
 *
 * Access control will be added with authentication; for now the routes are open.
 */
#[Route('/consumption')]
class ConsumptionController extends AbstractController
{
    /**
     * Redirects to the current year's consumption page.
     */
    #[Route('', name: 'consumption_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('consumption_year', ['year' => (int) date('Y')]);
    }

    /**
     * Lists every reading recorded for the given year.
     */
    #[Route('/{year}', name: 'consumption_year', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function year(int $year, ConsumptionReadingRepository $readings): Response
    {
        return $this->render('consumption/index.html.twig', [
            'year' => $year,
            'readings' => $readings->findForYear($year),
        ]);
    }

    /**
     * Creates a new reading for the given year.
     */
    #[Route('/{year}/new', name: 'consumption_new', requirements: ['year' => '\d{4}'], methods: ['GET', 'POST'])]
    public function new(int $year, Request $request, EntityManagerInterface $em): Response
    {
        $reading = (new ConsumptionReading())->setPeriodYear($year);

        return $this->handleForm($reading, $year, $request, $em);
    }

    /**
     * Edits an existing reading. The {@see ConsumptionReading} is resolved from the {id} route
     * parameter by Symfony's entity value resolver.
     */
    #[Route('/{year}/{id}/edit', name: 'consumption_edit', requirements: ['year' => '\d{4}', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $year, ConsumptionReading $reading, Request $request, EntityManagerInterface $em): Response
    {
        if ($reading->getPeriodYear() !== $year) {
            throw $this->createNotFoundException('The reading does not belong to the given year.');
        }

        return $this->handleForm($reading, $year, $request, $em);
    }

    /**
     * Builds and processes the reading form, persisting on a valid submission.
     */
    private function handleForm(ConsumptionReading $reading, int $year, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ConsumptionReadingType::class, $reading);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($reading);
            $em->flush();
            $this->addFlash('success', 'Lectura de consumo guardada.');

            return $this->redirectToRoute('consumption_year', ['year' => $year]);
        }

        return $this->render('consumption/form.html.twig', [
            'form' => $form,
            'year' => $year,
            'reading' => $reading,
        ]);
    }
}
