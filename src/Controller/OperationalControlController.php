<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OperationalControlAnswer;
use App\Entity\OperationalControlCheck;
use App\Entity\OperationalControlItem;
use App\Enum\Area;
use App\Enum\OperationalControlSection;
use App\Form\OperationalControlCheckType;
use App\Repository\OperationalControlCheckRepository;
use App\Repository\OperationalControlItemRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Monthly operational-control inspections (PG-08.01 / RG-08.01.01): list them and fill in/edit the
 * checklist of a month. The checklist items come from the configurable catalogue, so the inspection
 * always reflects the current set of items.
 *
 * Requires authentication and per-area permission (Area::OPERATIONAL_CONTROL): READ to view, WRITE to change.
 */
#[Route('/operational-control')]
class OperationalControlController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OperationalControlItemRepository $items,
    ) {
    }

    /**
     * Lists the monthly inspections, newest month first.
     */
    #[Route('', name: 'operational_control_index', methods: ['GET'])]
    public function index(OperationalControlCheckRepository $checks): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::OPERATIONAL_CONTROL);

        return $this->render('operational_control/index.html.twig', [
            'checks' => $checks->findRecent(),
        ]);
    }

    /**
     * Starts the inspection of the current month, pre-filling one (unanswered) row per active item.
     */
    #[Route('/new', name: 'operational_control_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::OPERATIONAL_CONTROL);

        $now = new \DateTimeImmutable();
        $check = (new OperationalControlCheck())
            ->setPeriodYear((int) $now->format('Y'))
            ->setPeriodMonth((int) $now->format('n'));
        $this->ensureAnswers($check);

        return $this->handleForm($check, $request, $em);
    }

    /**
     * Edits an existing inspection, backfilling rows for any item added to the catalogue afterwards.
     */
    #[Route('/{id}/edit', name: 'operational_control_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(OperationalControlCheck $check, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::OPERATIONAL_CONTROL);
        $this->ensureAnswers($check);

        return $this->handleForm($check, $request, $em);
    }

    /**
     * Builds and processes the inspection form, persisting on a valid submission.
     */
    private function handleForm(OperationalControlCheck $check, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $check->getId();
        $form = $this->createForm(OperationalControlCheckType::class, $check);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($check);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'operational_control.created' : 'operational_control.updated',
                'OperationalControlCheck',
                (string) $check->getId(),
                sprintf('Control operacional %02d/%d · %d no conformes', $check->getPeriodMonth(), $check->getPeriodYear(), $check->countNonConform()),
            );
            $this->addFlash('success', 'Control operacional guardado.');

            return $this->redirectToRoute('operational_control_index');
        }

        return $this->render('operational_control/form.html.twig', [
            'form' => $form,
            'check' => $check,
            'isNew' => $isNew,
        ]);
    }

    /**
     * Adds an answer for every active catalogue item the check doesn't have yet, ordered by section
     * (checklist order) then position, so the form renders the items grouped and in order.
     */
    private function ensureAnswers(OperationalControlCheck $check): void
    {
        $answered = [];
        foreach ($check->getAnswers() as $answer) {
            $answered[$answer->getItem()->getId()] = true;
        }
        foreach ($this->orderedActiveItems() as $item) {
            if (!isset($answered[$item->getId()])) {
                $check->addAnswer((new OperationalControlAnswer())->setItem($item));
            }
        }
    }

    /**
     * Active catalogue items sorted by section (in the enum's declaration order) then position.
     *
     * @return OperationalControlItem[] the ordered active items
     */
    private function orderedActiveItems(): array
    {
        $order = array_flip(array_map(static fn (OperationalControlSection $s): string => $s->value, OperationalControlSection::cases()));
        $items = $this->items->findActiveOrdered();
        usort(
            $items,
            static fn (OperationalControlItem $a, OperationalControlItem $b): int => [$order[$a->getSection()->value], $a->getPosition()] <=> [$order[$b->getSection()->value], $b->getPosition()],
        );

        return $items;
    }
}
