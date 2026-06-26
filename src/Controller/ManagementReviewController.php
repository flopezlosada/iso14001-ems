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
use App\Service\ManagementReview\ManagementReviewPrefiller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    /**
     * Lists every management review, most recent course first.
     */
    #[Route('', name: 'management_review_index', methods: ['GET'])]
    public function index(ManagementReviewRepository $repository): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::MANAGEMENT_REVIEW);

        return $this->render('management_review/index.html.twig', [
            'reviews' => $repository->findAllOrdered(),
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
     * Records Direction's approval (sign-off) of the review. Idempotent: an already approved review
     * is left unchanged.
     */
    #[Route('/{id}/approve', name: 'management_review_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(ManagementReview $review, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::MANAGEMENT_REVIEW);

        $user = $this->getUser();
        if ($this->isCsrfTokenValid('approve'.$review->getId(), (string) $request->request->get('_token'))
            && !$review->isApproved()
            && $user instanceof User
        ) {
            $review->setApprovedBy($user);
            $review->setApprovedAt(new \DateTimeImmutable());
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
            $em->remove($review);
            $em->flush();

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
     * Builds and processes the review form. On a valid new review the sections are pre-filled from
     * the other modules before persisting; the user is then redirected to the edit page so the
     * generated sections appear.
     */
    private function handleForm(ManagementReview $review, Request $request, EntityManagerInterface $em, bool $lockExercise): Response
    {
        $isNew = null === $review->getId();

        $form = $this->createForm(ManagementReviewType::class, $review, ['lock_exercise' => $lockExercise]);
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
        ]);
    }
}
