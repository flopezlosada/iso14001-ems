<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CorrectiveAction;
use App\Entity\SystemAudit;
use App\Enum\Area;
use App\Enum\AuditType;
use App\Form\SystemAuditType;
use App\Repository\AuditLogRepository;
use App\Repository\NonConformityRepository;
use App\Repository\SystemAuditRepository;
use App\Security\Voter\AreaVoter;
use App\Service\AuditLogger;
use App\Service\AuditWorkflowStatusProvider;
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
    public function index(SystemAuditRepository $audits, AuditWorkflowStatusProvider $workflow): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SYSTEM_AUDIT);

        $currentYear = (int) date('Y');

        return $this->render('system_audit/index.html.twig', [
            'audits' => $audits->findAllOrdered(),
            'currentYear' => $currentYear,
            // Guía "qué falta este curso": pendientes de la auditoría interna del año, cada uno
            // enlazado a su acción. Sustituye al antiguo aviso puntual de "auditoría pendiente".
            'status' => $workflow->for($currentYear),
        ]);
    }

    /**
     * Shows an audit in detail, including the non-conformities raised in it.
     */
    #[Route('/{id}', name: 'system_audit_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(SystemAudit $audit, NonConformityRepository $nonConformities, AuditLogRepository $auditLogs): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SYSTEM_AUDIT);

        return $this->render('system_audit/show.html.twig', [
            'audit' => $audit,
            'findings' => $nonConformities->findByAudit($audit),
            'activity' => $auditLogs->findForSubject('SystemAudit', (string) $audit->getId()),
        ]);
    }

    /**
     * Generates the draft resolution plan for the audit: a corrective action (to be completed) for
     * each of its non-conformities that has none yet. This is the "design the actions" step once the
     * findings of the (external) report have been registered — the system seeds the plan's structure;
     * the responsible and dates are filled in afterwards. CSRF-protected POST, idempotent (a finding
     * that already has actions is skipped).
     */
    #[Route('/{id}/action-plan', name: 'system_audit_action_plan', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function generateActionPlan(
        SystemAudit $audit,
        Request $request,
        EntityManagerInterface $em,
        NonConformityRepository $nonConformities,
    ): Response {
        // Reading the audit and creating its findings' corrective actions: gate both areas, the
        // latter because the plan lives in the non-conformity area.
        $this->denyAccessUnlessGranted(AreaVoter::READ, Area::SYSTEM_AUDIT);
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::NONCONFORMITY);

        if (!$this->isCsrfTokenValid('action_plan_system_audit'.(string) $audit->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $created = 0;
        foreach ($nonConformities->findByAudit($audit) as $nonConformity) {
            if (!$nonConformity->getCorrectiveActions()->isEmpty()) {
                continue;
            }

            $em->persist(
                (new CorrectiveAction())
                    ->setNonConformity($nonConformity)
                    // The guard above guarantees this finding has no actions yet, so it is the first.
                    ->setSequence(1)
                    ->setDescription(sprintf(
                        'Acción correctiva para subsanar la no conformidad %s (pendiente de definir).',
                        $nonConformity->getReference(),
                    )),
            );
            ++$created;
        }
        $em->flush();

        if ($created > 0) {
            $this->auditLogger->log('systemaudit.actionplan_generated', 'SystemAudit', (string) $audit->getId(), $this->label($audit));
            $this->addFlash('success', sprintf('Borrador del plan de acciones generado: %d acción(es) creada(s). Completa responsable y fechas.', $created));
        } else {
            $this->addFlash('info', 'No hay no conformidades sin plan de acciones en esta auditoría.');
        }

        return $this->redirectToRoute('system_audit_show', ['id' => $audit->getId()]);
    }

    /**
     * Registers a new audit.
     */
    #[Route('/new', name: 'system_audit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AreaVoter::WRITE, Area::SYSTEM_AUDIT);

        $audit = (new SystemAudit())->setYear((int) date('Y'));
        $type = AuditType::tryFrom((string) $request->query->get('type'));
        if (null !== $type) {
            $audit->setType($type);
        }

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
