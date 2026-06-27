<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CorrectiveAction;
use App\Entity\NonConformity;
use App\Entity\SystemAudit;
use App\Entity\User;
use App\Enum\Efficacy;
use App\Enum\NonConformityOrigin;
use App\Enum\NonConformityStatus;
use App\Enum\ProcessType;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Non-conformities and their corrective actions (PC.10.0, F.11.0). Sample DEMO data covering the
 * three lifecycle states (closed, in treatment, open) and the centre's reference format
 * NC.&lt;origin&gt;.&lt;year&gt;.&lt;NN&gt;.
 */
final class NonConformityFixtures extends AbstractDemoFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $closed = $this->closedFinding();
        $inTreatment = $this->inTreatmentFinding();
        $open = $this->openFinding();

        foreach ([$closed, $inTreatment, $open] as $finding) {
            $nc = new NonConformity();
            $nc->setReference($finding['reference'])
                ->setOrigin($finding['origin'])
                ->setOriginDetail($finding['originDetail'])
                ->setYear($finding['year'])
                ->setSequence($finding['sequence'])
                ->setIsoClause($finding['isoClause'])
                ->setAffectedProcess($finding['process'])
                ->setDescription($finding['description'])
                ->setRootCause($finding['rootCause'])
                ->setResponsible($this->getReference(UserFixtures::ref($finding['responsible']), User::class))
                ->setStatus($finding['status'])
                ->setOpenedAt(new \DateTimeImmutable($finding['openedAt']))
                ->setClosedAt(null !== $finding['closedAt'] ? new \DateTimeImmutable($finding['closedAt']) : null);
            if (null !== $finding['audit']) {
                $nc->setAudit($this->getReference(SystemAuditFixtures::ref($finding['audit']), SystemAudit::class));
            }
            $manager->persist($nc);

            foreach ($finding['actions'] as $actionData) {
                $action = $this->buildAction($nc, $actionData);
                $manager->persist($action);
            }
        }

        $manager->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildAction(NonConformity $nc, array $data): CorrectiveAction
    {
        $action = new CorrectiveAction();
        $action->setNonConformity($nc)
            ->setSequence($data['sequence'])
            ->setDescription($data['description'])
            ->setResponsible($this->getReference(UserFixtures::ref($data['responsible']), User::class))
            ->setPlannedDate(new \DateTimeImmutable($data['plannedDate']))
            ->setImplementationEvidence($data['evidence'] ?? null)
            ->setRequiresDirectionAuthorization($data['requiresAuth'] ?? false);

        if (!empty($data['authorizedBy'])) {
            $action->setAuthorizedBy($this->getReference(UserFixtures::ref($data['authorizedBy']), User::class))
                ->setAuthorizedAt(new \DateTimeImmutable($data['authorizedAt']));
        }
        if (!empty($data['reviewedBy'])) {
            $action->setReviewedBy($this->getReference(UserFixtures::ref($data['reviewedBy']), User::class))
                ->setReviewedAt(new \DateTimeImmutable($data['reviewedAt']))
                ->setEfficacy($data['efficacy']);
        }

        return $action;
    }

    /** @return array<string, mixed> */
    private function closedFinding(): array
    {
        return [
            'reference' => 'NC.AE.2025.01',
            'origin' => NonConformityOrigin::EXTERNAL_AUDIT,
            'originDetail' => 'Auditoría de seguimiento SGS 2025',
            'year' => 2025, 'sequence' => 1, 'isoClause' => '8.1', 'process' => ProcessType::KEY,
            'description' => 'El almacenamiento temporal de residuos peligrosos supera los 6 meses permitidos.',
            'rootCause' => 'Falta de un calendario de recogidas con el gestor autorizado.',
            'responsible' => 'calidad', 'status' => NonConformityStatus::CLOSED,
            'audit' => 'external-2025',
            'openedAt' => '2025-03-18', 'closedAt' => '2025-06-30',
            'actions' => [
                ['sequence' => 1, 'responsible' => 'mantenimiento', 'plannedDate' => '2025-04-30',
                    'description' => 'Acordar un calendario trimestral de recogidas con el gestor autorizado.',
                    'evidence' => 'Contrato de recogida trimestral firmado el 25/04/2025.',
                    'reviewedBy' => 'calidad', 'reviewedAt' => '2025-06-30', 'efficacy' => Efficacy::OK],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function inTreatmentFinding(): array
    {
        return [
            'reference' => 'NC.AI.2026.01',
            'origin' => NonConformityOrigin::INTERNAL_AUDIT,
            'originDetail' => 'Auditoría interna enero 2026',
            'year' => 2026, 'sequence' => 1, 'isoClause' => '7.5', 'process' => ProcessType::SUPPORT,
            'description' => 'Varios registros del SGA no están en su última versión vigente.',
            'rootCause' => 'No se revisó la lista de documentación vigente tras los últimos cambios.',
            'responsible' => 'sga', 'status' => NonConformityStatus::IN_TREATMENT,
            'audit' => 'internal-2026',
            'openedAt' => '2026-01-22', 'closedAt' => null,
            'actions' => [
                ['sequence' => 1, 'responsible' => 'sga', 'plannedDate' => '2026-03-15',
                    'description' => 'Actualizar la F.01.0 y verificar las versiones vigentes de todos los registros.',
                    'requiresAuth' => true, 'authorizedBy' => 'direccion', 'authorizedAt' => '2026-01-25'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function openFinding(): array
    {
        return [
            'reference' => 'NC.I.2026.01',
            'origin' => NonConformityOrigin::INTERNAL,
            'originDetail' => 'Observación del personal de mantenimiento',
            'year' => 2026, 'sequence' => 1, 'isoClause' => '8.2', 'process' => ProcessType::KEY,
            'description' => 'Pequeño derrame de producto de limpieza en el almacén sin contención disponible.',
            'rootCause' => null,
            'responsible' => 'mantenimiento', 'status' => NonConformityStatus::OPEN,
            'audit' => null,
            'openedAt' => '2026-05-09', 'closedAt' => null,
            'actions' => [],
        ];
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, SystemAuditFixtures::class];
    }
}
