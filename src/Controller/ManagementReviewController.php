<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ManagementReview;
use App\Entity\User;
use App\Enum\Area;
use App\Form\ManagementReviewType;
use App\Repository\ManagementReviewRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use App\Service\FileUploader;
use App\Service\ManagementReview\ManagementReviewPdfGenerator;
use App\Service\ManagementReview\ManagementReviewPrefiller;
use App\Service\ManagementReview\ManagementReviewWorkflowStatusProvider;
use App\Util\SchoolYear;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Management review of the EMS (PG-09.03.00 / RG-09.03.01, ISO 14001:2015 §9.3). One review per
 * course; on creation its sections are pre-filled with a frozen snapshot of the other modules'
 * data, then edited and finally approved (signed off) by Direction.
 *
 * Requires authentication and per-area permission (Area::MANAGEMENT_REVIEW): READ to view, WRITE to
 * manage.
 */
#[Route('/management-review')]
class ManagementReviewController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ManagementReviewPrefiller $prefiller,
        private readonly ManagementReviewPdfGenerator $pdfGenerator,
        private readonly FileUploader $fileUploader,
    ) {
    }

    /**
     * Lists every management review, most recent course first.
     */
    #[Route('', name: 'management_review_index', methods: ['GET'])]
    public function index(ManagementReviewRepository $repository, ManagementReviewWorkflowStatusProvider $workflow): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::MANAGEMENT_REVIEW);

        $currentExercise = SchoolYear::current(new \DateTimeImmutable());

        return $this->render('management_review/index.html.twig', [
            'reviews' => $repository->findAllOrdered(),
            'currentExercise' => $currentExercise,
            // Guía "qué falta este curso": pendientes del acta de la dirección, cada uno enlazado a
            // su acción.
            'status' => $workflow->for($currentExercise),
        ]);
    }

    /**
     * Shows a management review in detail (read-only), with its sections in order.
     */
    #[Route('/{id}', name: 'management_review_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ManagementReviewRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::MANAGEMENT_REVIEW);

        $review = $repository->findWithSections($id);
        if (null === $review) {
            throw $this->createNotFoundException();
        }

        return $this->render('management_review/show.html.twig', [
            'review' => $review,
        ]);
    }

    /**
     * Creates a review for a course: the meeting metadata is captured here and the sections are then
     * pre-filled from the other modules before redirecting to the edit page.
     */
    #[Route('/new', name: 'management_review_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::MANAGEMENT_REVIEW);

        return $this->handleForm(new ManagementReview(), $request, $em, lockExercise: false);
    }

    /**
     * Edits a review's metadata and section texts.
     */
    #[Route('/{id}/edit', name: 'management_review_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(ManagementReview $review, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::MANAGEMENT_REVIEW);

        // An approved review is a signed document (clause 7.5): immutable. Guard the route itself,
        // not just the UI, so a direct POST cannot overwrite it.
        if ($review->isApproved()) {
            $this->addFlash('error', 'La revisión está aprobada y no puede editarse.');

            return $this->redirectToRoute('management_review_show', ['id' => $review->getId()]);
        }

        return $this->handleForm($review, $request, $em, lockExercise: true);
    }

    /**
     * Regenerates the auto-generated sections from the current module data (the snapshots are
     * otherwise frozen at creation time). Only on a draft review; an approved one is immutable.
     * CSRF-protected POST.
     */
    #[Route('/{id}/regenerate', name: 'management_review_regenerate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function regenerate(int $id, ManagementReviewRepository $repository, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::MANAGEMENT_REVIEW);

        $review = $repository->findWithSections($id);
        if (null === $review) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('regenerate'.$review->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        if ($review->isApproved()) {
            $this->addFlash('error', 'La revisión está aprobada y no puede regenerarse.');

            return $this->redirectToRoute('management_review_show', ['id' => $review->getId()]);
        }

        $this->prefiller->refreshAutoSections($review);
        $em->flush();

        $this->addFlash('success', 'Secciones automáticas actualizadas con los datos actuales.');

        return $this->redirectToRoute('management_review_edit', ['id' => $review->getId()]);
    }

    /**
     * Records Direction's approval (sign-off) of the review. Idempotent: an already approved review
     * is left unchanged.
     */
    #[Route('/{id}/approve', name: 'management_review_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(int $id, ManagementReviewRepository $repository, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::MANAGEMENT_REVIEW);

        // Eager-load sections and participants: sealing renders the PDF, which reads both.
        $review = $repository->findWithSections($id);
        if (null === $review) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        if ($this->isCsrfTokenValid('approve'.$review->getId(), (string) $request->request->get('_token'))
            && !$review->isApproved()
            && $user instanceof User
        ) {
            $review->setApprovedBy($user);
            $review->setApprovedAt(new \DateTimeImmutable());

            // Seal the official PDF (RG-09.03.01): persist it as the immutable artefact and hash its
            // bytes. The integrity hash certifies this exact stored file, which is why it is saved
            // rather than regenerated on demand (dompdf output is not byte-for-byte reproducible).
            $pdf = $this->pdfGenerator->render($review);
            $review->setStoragePath($this->fileUploader->store($pdf, 'management-review-pdfs', 'pdf'));
            $review->setIntegrityHash(hash('sha256', $pdf));

            $em->flush();

            $this->auditLogger->log(
                'managementreview.approved',
                'ManagementReview',
                (string) $review->getId(),
                sprintf('Revisión por la dirección del curso %s aprobada.', $review->getExercise()),
            );
            $this->addFlash('success', 'Revisión por la dirección aprobada.');
        }

        return $this->redirectToRoute('management_review_show', ['id' => $review->getId()]);
    }

    /**
     * Deletes a review.
     */
    #[Route('/{id}/delete', name: 'management_review_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(ManagementReview $review, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::MANAGEMENT_REVIEW);

        if ($this->isCsrfTokenValid('delete'.$review->getId(), (string) $request->request->get('_token'))) {
            $exercise = $review->getExercise();
            // Capture the on-disk artefacts before the entity is gone, then remove them only after a
            // successful flush: the shared hosting's hard limit is inodes, so orphan PDFs must not pile up.
            $storagePath = $review->getStoragePath();
            $signedPath = $review->getSignedPdfPath();

            $em->remove($review);
            $em->flush();

            foreach (array_filter([$storagePath, $signedPath]) as $path) {
                $this->fileUploader->remove($path);
            }

            $this->auditLogger->log(
                'managementreview.deleted',
                'ManagementReview',
                null,
                sprintf('Revisión por la dirección del curso %s eliminada.', $exercise),
            );
            $this->addFlash('success', 'Revisión por la dirección eliminada.');
        }

        return $this->redirectToRoute('management_review_index');
    }

    /**
     * Serves the official report (RG-09.03.01) of a review as a PDF. An approved review serves the
     * sealed PDF that its integrity hash certifies; a draft gets a live preview so it can be reviewed
     * before approval.
     */
    #[Route('/{id}/pdf', name: 'management_review_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadPdf(int $id, ManagementReviewRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::MANAGEMENT_REVIEW);

        $review = $repository->findWithSections($id);
        if (null === $review) {
            throw $this->createNotFoundException();
        }

        $stored = $review->getStoragePath();
        if (null !== $stored && $review->isApproved()) {
            return $this->file($this->fileUploader->absolutePath($stored), $this->pdfFilename($review));
        }

        return new Response($this->pdfGenerator->render($review), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfFilename($review).'"',
        ]);
    }

    /**
     * Attaches the PDF signed by Direction with their own certificate (level 1a, "upload the signed
     * PDF" via AutoFirma). Only an already-approved review can carry a signature.
     */
    #[Route('/{id}/firma', name: 'management_review_sign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadSignedPdf(ManagementReview $review, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::MANAGEMENT_REVIEW);

        $redirect = $this->redirectToRoute('management_review_show', ['id' => $review->getId()]);
        if (!$this->isCsrfTokenValid('sign'.$review->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }
        if (!$review->isApproved()) {
            $this->addFlash('error', 'Solo se puede adjuntar la firma de una revisión ya aprobada.');

            return $redirect;
        }

        $file = $request->files->get('signedPdf');
        if (!$file instanceof UploadedFile || 'application/pdf' !== $file->getMimeType()) {
            $this->addFlash('error', 'Adjunta el PDF firmado (formato PDF).');

            return $redirect;
        }

        $previous = $review->getSignedPdfPath();
        $review->setSignedPdfPath($this->fileUploader->upload($file, 'management-review-signed'));
        if (null !== $previous) {
            $this->fileUploader->remove($previous);
        }

        $em->flush();
        $this->auditLogger->log(
            'managementreview.signed',
            'ManagementReview',
            (string) $review->getId(),
            sprintf('Firma adjunta a la revisión por la dirección del curso %s.', $review->getExercise()),
        );
        $this->addFlash('success', 'PDF firmado adjuntado a la revisión.');

        return $redirect;
    }

    /**
     * Serves the level-1a signed PDF attached to a review.
     */
    #[Route('/{id}/firma', name: 'management_review_signed_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadSignedPdf(ManagementReview $review): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::MANAGEMENT_REVIEW);

        $signed = $review->getSignedPdfPath();
        if (null === $signed) {
            throw $this->createNotFoundException('No hay PDF firmado para esta revisión.');
        }

        return $this->file($this->fileUploader->absolutePath($signed), $this->pdfFilename($review, 'firmado'));
    }

    /**
     * A human-readable download name for a review's PDF, e.g. "RG-09.03.01-2025-2026.pdf".
     */
    private function pdfFilename(ManagementReview $review, ?string $suffix = null): string
    {
        return 'RG-09.03.01-'.$review->getExercise().(null !== $suffix ? '-'.$suffix : '').'.pdf';
    }

    /**
     * Builds and processes the review form. On a valid new review the sections are pre-filled from
     * the other modules before persisting; the user is then redirected to the edit page so the
     * generated sections appear.
     */
    private function handleForm(ManagementReview $review, Request $request, EntityManagerInterface $em, bool $lockExercise): Response
    {
        $isNew = null === $review->getId();
        $autoKeys = $this->prefiller->autoGeneratedKeys();

        $form = $this->createForm(ManagementReviewType::class, $review, [
            'lock_exercise' => $lockExercise,
            'auto_keys' => $autoKeys,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $this->prefiller->prefill($review);
            }

            $em->persist($review);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'managementreview.created' : 'managementreview.updated',
                'ManagementReview',
                (string) $review->getId(),
                sprintf('Revisión por la dirección del curso %s.', $review->getExercise()),
            );
            $this->addFlash('success', $isNew ? 'Revisión creada; revisa y completa las secciones.' : 'Revisión guardada.');

            return $this->redirectToRoute('management_review_edit', ['id' => $review->getId()]);
        }

        return $this->render('management_review/form.html.twig', [
            'form' => $form,
            'review' => $review,
            'isNew' => $isNew,
            'autoKeys' => $autoKeys,
        ]);
    }
}
