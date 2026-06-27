<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SystemAudit;
use App\Enum\Area;
use App\Form\SystemAuditType;
use App\Repository\NonConformityRepository;
use App\Repository\SystemAuditRepository;
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
 * Register of management-system audits (PC.09.0, ISO 14001:2015 §9.2): list the audits, add/edit
 * one with its report, and see an audit with the non-conformities it raised.
 *
 * Requires authentication and per-area permission (Area::SYSTEM_AUDIT): READ to view, WRITE to
 * register, edit or delete.
 */
#[Route('/system-audits')]
class SystemAuditController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FileUploader $fileUploader,
    ) {
    }

    /**
     * Lists every audit (most recent first).
     */
    #[Route('', name: 'system_audit_index', methods: ['GET'])]
    public function index(SystemAuditRepository $audits): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SYSTEM_AUDIT);

        return $this->render('system_audit/index.html.twig', [
            'audits' => $audits->findAllOrdered(),
        ]);
    }

    /**
     * Shows an audit in detail, including the non-conformities raised in it.
     */
    #[Route('/{id}', name: 'system_audit_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(SystemAudit $audit, NonConformityRepository $nonConformities): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SYSTEM_AUDIT);

        return $this->render('system_audit/show.html.twig', [
            'audit' => $audit,
            'findings' => $nonConformities->findByAudit($audit),
        ]);
    }

    /**
     * Registers a new audit.
     */
    #[Route('/new', name: 'system_audit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SYSTEM_AUDIT);

        $audit = (new SystemAudit())->setYear((int) date('Y'));

        return $this->handleForm($audit, $request, $em);
    }

    /**
     * Edits an existing audit. The {@see SystemAudit} is resolved from the {id} route parameter by
     * Symfony's entity value resolver.
     */
    #[Route('/{id}/edit', name: 'system_audit_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(SystemAudit $audit, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SYSTEM_AUDIT);

        return $this->handleForm($audit, $request, $em);
    }

    /**
     * Serves the audit's report file with its original name.
     */
    #[Route('/{id}/report', name: 'system_audit_report', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function report(SystemAudit $audit): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SYSTEM_AUDIT);

        if (!$audit->hasReport()) {
            throw $this->createNotFoundException('La auditoría no tiene informe adjunto.');
        }

        return $this->file(
            $this->fileUploader->absolutePath((string) $audit->getReportPath()),
            $audit->getReportOriginalName() ?? 'informe-auditoria',
        );
    }

    /**
     * Deletes an audit (and its report file). Its non-conformities are kept, only unlinked
     * (FK ON DELETE SET NULL). CSRF-protected POST.
     */
    #[Route('/{id}/delete', name: 'system_audit_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(SystemAudit $audit, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SYSTEM_AUDIT);

        if (!$this->isCsrfTokenValid('delete_system_audit'.(string) $audit->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $id = (string) $audit->getId();
        $label = $this->label($audit);
        $reportPath = $audit->getReportPath();

        $em->remove($audit);
        $em->flush();

        if (null !== $reportPath) {
            $this->fileUploader->remove($reportPath);
        }

        $this->auditLogger->log('systemaudit.deleted', 'SystemAudit', $id, $label);
        $this->addFlash('success', sprintf('Auditoría «%s» eliminada.', $label));

        return $this->redirectToRoute('system_audit_index');
    }

    /**
     * Builds and processes the audit form, persisting on a valid submission and storing the report
     * file (replacing any previous one) when provided.
     */
    private function handleForm(SystemAudit $audit, Request $request, EntityManagerInterface $em): Response
    {
        $isNew = null === $audit->getId();

        $form = $this->createForm(SystemAuditType::class, $audit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reportFile = $form->get('reportFile')->getData();
            $previousReport = null;
            if ($reportFile instanceof UploadedFile) {
                $previousReport = $audit->getReportPath();
                $audit
                    ->setReportPath($this->fileUploader->upload($reportFile, 'audit-reports'))
                    ->setReportOriginalName($reportFile->getClientOriginalName());
            }

            $em->persist($audit);
            $em->flush();

            // Remove the replaced file only after the new path is safely persisted, so a failed
            // flush never leaves the entity pointing at a deleted file.
            if (null !== $previousReport) {
                $this->fileUploader->remove($previousReport);
            }

            $this->auditLogger->log(
                $isNew ? 'systemaudit.created' : 'systemaudit.updated',
                'SystemAudit',
                (string) $audit->getId(),
                $this->label($audit),
            );
            $this->addFlash('success', 'Auditoría guardada.');

            return $this->redirectToRoute('system_audit_show', ['id' => $audit->getId()]);
        }

        return $this->render('system_audit/form.html.twig', [
            'form' => $form,
            'audit' => $audit,
            'isNew' => $isNew,
        ]);
    }

    /**
     * Short human label for flashes and the audit log, e.g. "Auditoría interna 2025".
     */
    private function label(SystemAudit $audit): string
    {
        return sprintf('Auditoría %s %d', $audit->getType()->label(), $audit->getYear());
    }
}
