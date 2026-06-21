<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\WasteRecord;
use App\Enum\Area;
use App\Form\WasteRecordType;
use App\Repository\WasteRecordRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
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

    #[Route('', name: 'waste_index', methods: ['GET'])]
    public function index(WasteRecordRepository $records): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::WASTE);

        return $this->render('waste/index.html.twig', [
            'records' => $records->findRecent(),
        ]);
    }

    #[Route('/new', name: 'waste_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::WASTE);

        return $this->handleForm(new WasteRecord(), $request, $em);
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
                sprintf('Residuo %s · %s kg · %s', $record->getLerCode(), $record->getQuantityKg(), $record->getPickupDate()->format('d/m/Y')),
            );
            $this->addFlash('success', 'Registro de residuo guardado.');

            return $this->redirectToRoute('waste_index');
        }

        return $this->render('waste/form.html.twig', [
            'form' => $form,
            'record' => $record,
        ]);
    }
}
