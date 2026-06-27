<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SystemAudit;
use App\Enum\AuditType;
use Doctrine\Persistence\ObjectManager;

/**
 * Management-system audits (PC.09.0). Sample DEMO data: one external follow-up audit and one
 * internal audit, mirroring the kind of audits an education centre runs. Generic, synthetic data
 * (no real personal data, no centre name) — safe for git. Their non-conformities are linked from
 * {@see NonConformityFixtures}.
 */
final class SystemAuditFixtures extends AbstractDemoFixture
{
    /** Reference name for the audit with the given key, so other fixtures can wire to it. */
    public static function ref(string $key): string
    {
        return 'system-audit-'.$key;
    }

    public function load(ObjectManager $manager): void
    {
        // key => [type, year, conductedOn|null, auditor, objective|null, scope|null, conclusions|null]
        /** @var array<string, array{0: AuditType, 1: int, 2: ?string, 3: string, 4: ?string, 5: ?string, 6: ?string}> $audits */
        $audits = [
            'external-2025' => [
                AuditType::EXTERNAL, 2025, '2025-04-28', 'Entidad certificadora (auditoría de seguimiento)',
                'Verificar el mantenimiento del sistema de gestión ambiental conforme a ISO 14001:2015.',
                'Sistema de gestión ambiental del centro educativo.',
                'Sistema conforme. Se detecta una no conformidad menor relativa al almacenamiento de residuos.',
            ],
            'internal-2026' => [
                AuditType::INTERNAL, 2026, '2026-01-22', 'Auditora interna del centro',
                'Identificar el grado de implantación del sistema integrado ISO 14001:2015.',
                'Capítulos 4 a 10 de la norma; todos los procesos del centro.',
                null, // auditoría sin conclusiones formalizadas todavía: forma de dato realista
            ],
        ];

        foreach ($audits as $key => [$type, $year, $conductedOn, $auditor, $objective, $scope, $conclusions]) {
            $audit = new SystemAudit();
            $audit->setType($type)
                ->setYear($year)
                ->setConductedOn(null !== $conductedOn ? new \DateTimeImmutable($conductedOn) : null)
                ->setAuditor($auditor)
                ->setObjective($objective)
                ->setScope($scope)
                ->setConclusions($conclusions);
            $manager->persist($audit);
            $this->addReference(self::ref($key), $audit);
        }

        $manager->flush();
    }
}
