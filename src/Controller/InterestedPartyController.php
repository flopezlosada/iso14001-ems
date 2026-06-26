<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InterestedParty;
use App\Enum\Area;
use App\Form\InterestedPartyType;
use App\Repository\InterestedPartyRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annual register of interested parties (form F.04.0 / PPI, ISO 14001 clause 4.2): list a year's
 * interested parties and add/edit/delete one. A plain CRUD module with no scoring engine.
 *
 * Requires authentication and per-area permission (Area::INTERESTED_PARTY): READ to view, WRITE to
 * register, edit or delete.
 */
#[Route('/interested-parties')]
class InterestedPartyController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Redirects to the current year's interested-parties register.
     */
    #[Route('', name: 'interested_party_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::INTERESTED_PARTY);

        return $this->redirectToRoute('interested_party_year', ['year' => (int) date('Y')]);
    }

    /**
     * Lists every interested party recorded for the given review year.
     */
    #[Route('/{year}', name: 'interested_party_year', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function year(int $year, InterestedPartyRepository $parties): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::INTERESTED_PARTY);

        return $this->render('interested_party/index.html.twig', [
            'year' => $year,
            'parties' => $parties->findForYear($year),
        ]);
    }

    /**
     * Creates a new interested party for the given review year.
     */
    #[Route('/{year}/new', name: 'interested_party_new', requirements: ['year' => '\d{4}'], methods: ['GET', 'POST'])]
    public function new(int $year, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::INTERESTED_PARTY);

        $party = (new InterestedParty())->setReviewYear($year);

        return $this->handleForm($party, $year, $request, $em);
    }

    /**
     * Edits an existing interested party. The {@see InterestedParty} is resolved from the {id} route
     * parameter by Symfony's entity value resolver.
     */
    #[Route('/{year}/{id}/edit', name: 'interested_party_edit', requirements: ['year' => '\d{4}', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $year, InterestedParty $party, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::INTERESTED_PARTY);

        if ($party->getReviewYear() !== $year) {
            throw $this->createNotFoundException('The interested party does not belong to the given year.');
        }

        return $this->handleForm($party, $year, $request, $em);
    }

    /**
     * Deletes an interested party. CSRF-protected POST; redirects back to its review year.
     */
    #[Route('/{year}/{id}/delete', name: 'interested_party_delete', requirements: ['year' => '\d{4}', 'id' => '\d+'], methods: ['POST'])]
    public function delete(int $year, InterestedParty $party, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::INTERESTED_PARTY);

        if ($party->getReviewYear() !== $year) {
            throw $this->createNotFoundException('The interested party does not belong to the given year.');
        }

        if (!$this->isCsrfTokenValid('delete_interested_party'.(string) $party->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $id = (string) $party->getId();
        $name = $party->getName();

        $em->remove($party);
        $em->flush();

        $this->auditLogger->log('interestedparty.deleted', 'InterestedParty', $id, $name);
        $this->addFlash('success', sprintf('Parte interesada «%s» eliminada.', $name));

        return $this->redirectToRoute('interested_party_year', ['year' => $year]);
    }

    /**
     * Builds and processes the interested-party form, persisting on a valid submission.
     */
    private function handleForm(InterestedParty $party, int $year, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $party->getId();

        $form = $this->createForm(InterestedPartyType::class, $party);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($party);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'interestedparty.created' : 'interestedparty.updated',
                'InterestedParty',
                (string) $party->getId(),
                $party->getName(),
            );
            $this->addFlash('success', 'Parte interesada guardada.');

            return $this->redirectToRoute('interested_party_year', ['year' => $year]);
        }

        return $this->render('interested_party/form.html.twig', [
            'form' => $form,
            'year' => $year,
            'party' => $party,
            'isNew' => $isNew,
        ]);
    }
}
