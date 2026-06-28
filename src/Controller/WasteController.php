<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\WasteRecord;
use App\Enum\Area;
use App\Form\WasteRecordType;
use App\Repository\WasteRecordRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use App\Service\YearlyTrendChart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Chronological waste register (archivo cronológico de residuos): list pick-ups and add/edit one.
 *
 * Requires authentication and per-area permission (Area::WASTE): READ to view, WRITE to change.
 */
#[Route('/waste')]
class WasteController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Redirects to the current year's waste register.
     */
    #[Route('', name: 'waste_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::WASTE);

        return $this->redirectToRoute('waste_year', ['year' => (int) date('Y')]);
    }

    /**
     * Multi-year waste trend: the yearly total of kilograms removed over the last N years, split into
     * all waste, hazardous and non-hazardous, as a simple bar chart. Exploratory view only — it does
     * not feed the official aspect evaluation or alerts. (The interactive/styled chart is left to the
     * frontend; this is the data + a minimal CSS chart, shared with the consumption trend.)
     */
    #[Route('/tendencia', name: 'waste_trend', methods: ['GET'])]
    public function trend(Request $request, WasteRecordRepository $records, YearlyTrendChart $chart): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::WASTE);

        $years = max(2, min(10, $request->query->getInt('years', 5)));

        // Total plus the hazardous breakdown — the bounded, non-nullable classification that matters
        // most for an EMS. Grouping by LER code is deliberately avoided: it is nullable in the real
        // register and would explode into dozens of sparse series.
        $breakdown = [
            ['label' => 'Total de residuos', 'hazardous' => null],
            ['label' => 'Residuos peligrosos', 'hazardous' => true],
            ['label' => 'Residuos no peligrosos', 'hazardous' => false],
        ];

        $series = [];
        foreach ($breakdown as $def) {
            $points = $chart->points($records->yearlyTotalsKg($def['hazardous']), $years);
            if ([] === $points) {
                continue;
            }

            $series[] = ['label' => $def['label'], 'unit' => 'kg', 'points' => $points];
        }

        return $this->render('waste/trend.html.twig', [
            'series' => $series,
            'years' => $years,
        ]);
    }

    /**
     * Lists the pick-ups with no date assigned (historical entries dated in free text). Kept apart
     * from the per-year views, which can only show dated records, so these are never lost.
     */
    #[Route('/sin-fecha', name: 'waste_undated', methods: ['GET'])]
    public function undated(WasteRecordRepository $records): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::WASTE);

        return $this->render('waste/undated.html.twig', [
            'records' => $records->findUndated(),
        ]);
    }

    #[Route('/new', name: 'waste_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::WASTE);

        return $this->handleForm(new WasteRecord(), $request, $em);
    }

    /**
     * Lists the dated pick-ups of the given year, with a notice linking to the undated ones. Declared
     * after the literal routes (/new, /sin-fecha, /tendencia) so the dynamic {year} never shadows them.
     */
    #[Route('/{year}', name: 'waste_year', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function year(int $year, WasteRecordRepository $records): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::WASTE);

        return $this->render('waste/index.html.twig', [
            'year' => $year,
            'records' => $records->findForYear($year),
            'undatedCount' => $records->countUndated(),
        ]);
    }

    #[Route('/{id}/edit', name: 'waste_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(WasteRecord $record, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::WASTE);

        return $this->handleForm($record, $request, $em);
    }

    private function handleForm(WasteRecord $record, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(WasteRecordType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = null === $record->getId();
            $em->persist($record);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'waste.created' : 'waste.updated',
                'WasteRecord',
                (string) $record->getId(),
                sprintf(
                    'Residuo %s · %s kg · %s',
                    $record->getLerCode() ?? '—',
                    $record->getQuantityKg() ?? '—',
                    $record->getPickupDate()?->format('d/m/Y') ?? '—',
                ),
            );
            $this->addFlash('success', 'Registro de residuo guardado.');

            // Land on the page where the record actually shows: its pick-up year, or the undated
            // list when it has no date — not always the current year, which might not contain it.
            $pickupDate = $record->getPickupDate();

            return null !== $pickupDate
                ? $this->redirectToRoute('waste_year', ['year' => (int) $pickupDate->format('Y')])
                : $this->redirectToRoute('waste_undated');
        }

        return $this->render('waste/form.html.twig', [
            'form' => $form,
            'record' => $record,
        ]);
    }
}
