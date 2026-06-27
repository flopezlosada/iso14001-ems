<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TrainingEvidence;
use App\Enum\Area;
use App\Form\TrainingEvidenceType;
use App\Repository\TrainingEvidenceRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Register of environmental training evidence ("Registro de evidencias de formación ambiental",
 * ISO 14001:2015 §7.2/§7.3, obligation #18): who actually received which training, when, and
 * whether they completed the comprehension questionnaire. A plain CRUD module backed by a flat
 * evidence log (newest first), complementing the annual training plan ({@see TrainingController}).
 *
 * Requires authentication and per-area permission (Area::TRAINING): READ to view, WRITE to record,
 * edit or delete.
 */
#[Route('/training-evidences')]
class TrainingEvidenceController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Lists every recorded training evidence, newest first.
     */
    #[Route('', name: 'training_evidence_index', methods: ['GET'])]
    public function index(TrainingEvidenceRepository $evidences): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::TRAINING);

        return $this->render('training_evidence/index.html.twig', [
            'evidences' => $evidences->findAllOrdered(),
        ]);
    }

    /**
     * Records a new training evidence.
     */
    #[Route('/new', name: 'training_evidence_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::TRAINING);

        return $this->handleForm(new TrainingEvidence(), $request, $em);
    }

    /**
     * Edits an existing training evidence. The {@see TrainingEvidence} is resolved from the {id}
     * route parameter by Symfony's entity value resolver.
     */
    #[Route('/{id}/edit', name: 'training_evidence_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(TrainingEvidence $evidence, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::TRAINING);

        return $this->handleForm($evidence, $request, $em);
    }

    /**
     * Deletes a training evidence. CSRF-protected POST; redirects back to the listing.
     */
    #[Route('/{id}/delete', name: 'training_evidence_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(TrainingEvidence $evidence, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::TRAINING);

        if (!$this->isCsrfTokenValid('delete_training_evidence'.(string) $evidence->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $id = (string) $evidence->getId();
        $personName = $evidence->getPersonName();
        $label = sprintf('%s · %s', $personName, $evidence->getTrainingDescription());

        $em->remove($evidence);
        $em->flush();

        $this->auditLogger->log('training_evidence.deleted', 'TrainingEvidence', $id, $label);
        $this->addFlash('success', sprintf('Evidencia de «%s» eliminada.', $personName));

        return $this->redirectToRoute('training_evidence_index');
    }

    /**
     * Builds and processes the training evidence form, persisting on a valid submission.
     */
    private function handleForm(TrainingEvidence $evidence, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $evidence->getId();

        $form = $this->createForm(TrainingEvidenceType::class, $evidence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evidence);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'training_evidence.created' : 'training_evidence.updated',
                'TrainingEvidence',
                (string) $evidence->getId(),
                sprintf('%s · %s', $evidence->getPersonName(), $evidence->getTrainingDescription()),
            );
            $this->addFlash('success', 'Evidencia de formación guardada.');

            return $this->redirectToRoute('training_evidence_index');
        }

        return $this->render('training_evidence/form.html.twig', [
            'form' => $form,
            'evidence' => $evidence,
            'isNew' => $isNew,
        ]);
    }
}
