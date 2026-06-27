<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DafoAnalysis;
use App\Enum\Area;
use App\Form\DafoAnalysisType;
use App\Repository\DafoAnalysisRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * List of DAFO (SWOT) analyses and the registration/edit/delete form (register "F.06.0 DAFO",
 * ISO 14001 clause 4.1). One analysis per school year, with four free-text quadrants.
 *
 * Requires authentication and per-area permission (Area::DAFO): READ to view, WRITE to register,
 * edit or delete.
 */
#[Route('/dafo')]
class DafoController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every DAFO analysis, most recent school year first.
     */
    #[Route('', name: 'dafo_index', methods: ['GET'])]
    public function index(DafoAnalysisRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::DAFO);

        return $this->render('dafo/index.html.twig', [
            'analyses' => $repository->findAllOrdered(),
        ]);
    }

    /**
     * Registers a new DAFO analysis.
     */
    #[Route('/new', name: 'dafo_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::DAFO);

        return $this->handleForm(new DafoAnalysis(), $request, $em);
    }

    /**
     * Clones a DAFO analysis into the following school year as an editable draft, carrying over the
     * four SWOT quadrants without touching the source. Does nothing if an analysis already exists for
     * that next year, so it never overwrites. The user lands on the edit form to review the draft.
     * CSRF-protected POST.
     */
    #[Route('/{id}/clone-next', name: 'dafo_clone_next', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cloneToNextYear(DafoAnalysis $analysis, Request $request, EntityManagerInterface $em, DafoAnalysisRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::DAFO);

        if (!$this->isCsrfTokenValid('dafo_clone_next'.(string) $analysis->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $nextSchoolYear = $analysis->nextSchoolYear();

        if ($repository->existsForSchoolYear($nextSchoolYear)) {
            $this->addFlash('success', sprintf('Ya existe un análisis DAFO para %s; no se ha sobrescrito.', $nextSchoolYear));

            return $this->redirectToRoute('dafo_index');
        }

        $copy = $analysis->copyForSchoolYear($nextSchoolYear);
        $em->persist($copy);
        $em->flush();

        $this->auditLogger->log(
            'dafo.cloned_from_previous',
            'DafoAnalysis',
            (string) $copy->getId(),
            sprintf('Clonado de %s a %s', $analysis->getSchoolYear(), $nextSchoolYear),
        );
        $this->addFlash('success', sprintf('Borrador del análisis DAFO %s creado a partir de %s. Revísalo y guárdalo.', $nextSchoolYear, $analysis->getSchoolYear()));

        return $this->redirectToRoute('dafo_edit', ['id' => $copy->getId()]);
    }

    /**
     * Shows a single DAFO analysis read-only, as the 2x2 SWOT matrix.
     */
    #[Route('/{id}', name: 'dafo_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(DafoAnalysis $analysis): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::DAFO);

        return $this->render('dafo/show.html.twig', [
            'analysis' => $analysis,
        ]);
    }

    /**
     * Edits an existing DAFO analysis.
     */
    #[Route('/{id}/edit', name: 'dafo_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(DafoAnalysis $analysis, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::DAFO);

        return $this->handleForm($analysis, $request, $em);
    }

    /**
     * Deletes a DAFO analysis after validating its CSRF token.
     */
    #[Route('/{id}/delete', name: 'dafo_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(DafoAnalysis $analysis, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::DAFO);

        if (!$this->isCsrfTokenValid('dafo_delete'.(string) $analysis->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $id = (string) $analysis->getId();
        $schoolYear = $analysis->getSchoolYear();

        $em->remove($analysis);
        $em->flush();

        $this->auditLogger->log('dafo.deleted', 'DafoAnalysis', $id, $schoolYear);
        $this->addFlash('success', sprintf('Análisis DAFO %s eliminado.', $schoolYear));

        return $this->redirectToRoute('dafo_index');
    }

    /**
     * Builds and processes the DAFO form, persisting on a valid submission and auditing the action.
     */
    private function handleForm(DafoAnalysis $analysis, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $analysis->getId();

        $form = $this->createForm(DafoAnalysisType::class, $analysis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($analysis);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'dafo.created' : 'dafo.updated',
                'DafoAnalysis',
                (string) $analysis->getId(),
                $analysis->getSchoolYear(),
            );
            $this->addFlash('success', sprintf('Análisis DAFO %s guardado.', $analysis->getSchoolYear()));

            return $this->redirectToRoute('dafo_index');
        }

        return $this->render('dafo/form.html.twig', [
            'form' => $form,
            'analysis' => $analysis,
            'isNew' => $isNew,
        ]);
    }
}
