<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConsumptionReading;
use App\Enum\Area;
use App\Form\ConsumptionReadingType;
use App\Repository\ConsumptionReadingRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Monthly consumption capture (form F-6.1.2): list a year's readings and add/edit one.
 *
 * Requires an authenticated user (ROLE_USER). Fine-grained per-role read/write permissions
 * will come with the permissions module.
 */
#[Route('/consumption')]
class ConsumptionController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FileUploader $fileUploader,
    ) {
    }

    /**
     * Redirects to the current year's consumption page.
     */
    #[Route('', name: 'consumption_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::CONSUMPTION);

        return $this->redirectToRoute('consumption_year', ['year' => (int) date('Y')]);
    }

    /**
     * Lists every reading recorded for the given year.
     */
    #[Route('/{year}', name: 'consumption_year', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function year(int $year, ConsumptionReadingRepository $readings): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::CONSUMPTION);

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
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::CONSUMPTION);

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
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::CONSUMPTION);

        if ($reading->getPeriodYear() !== $year) {
            throw $this->createNotFoundException('The reading does not belong to the given year.');
        }

        return $this->handleForm($reading, $year, $request, $em);
    }

    /**
     * Serves the invoice attached to a reading as a download. Requires read access; 404 when the
     * reading has no invoice or does not belong to the given year.
     */
    #[Route('/{year}/{id}/invoice', name: 'consumption_invoice', requirements: ['year' => '\d{4}', 'id' => '\d+'], methods: ['GET'])]
    public function invoice(int $year, ConsumptionReading $reading): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::CONSUMPTION);

        if ($reading->getPeriodYear() !== $year || !$reading->hasInvoice()) {
            throw $this->createNotFoundException('No invoice for this reading.');
        }

        return $this->file(
            $this->fileUploader->absolutePath($reading->getInvoicePath()),
            $reading->getInvoiceOriginalName() ?? 'factura',
        );
    }

    /**
     * Builds and processes the reading form, persisting on a valid submission.
     */
    private function handleForm(ConsumptionReading $reading, int $year, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ConsumptionReadingType::class, $reading);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = null === $reading->getId();

            $invoiceFile = $form->get('invoiceFile')->getData();
            if ($invoiceFile instanceof UploadedFile) {
                $previousInvoice = $reading->getInvoicePath();
                $reading
                    ->setInvoicePath($this->fileUploader->upload($invoiceFile, 'consumption-invoices'))
                    ->setInvoiceOriginalName($invoiceFile->getClientOriginalName());

                // Drop the replaced file so attachments don't pile up (the host's inode budget
                // is tight on shared hosting).
                if (null !== $previousInvoice) {
                    $this->fileUploader->remove($previousInvoice);
                }
            }

            $em->persist($reading);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'consumption.created' : 'consumption.updated',
                'ConsumptionReading',
                (string) $reading->getId(),
                sprintf('Consumo %s · %02d/%d', $reading->getType()->label(), $reading->getPeriodMonth(), $reading->getPeriodYear()),
            );
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
