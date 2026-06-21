<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AuditLog;
use Doctrine\Persistence\ObjectManager;

/**
 * A few activity-trail entries so the admin audit view is not empty (cláusula 7.5). Sample DEMO
 * data. The trail is append-only and stamps {@see AuditLog::$occurredAt} at construction, so these
 * entries all carry the load timestamp — enough to render the view, not a real chronology.
 */
final class AuditLogFixtures extends AbstractDemoFixture
{
    public function load(ObjectManager $manager): void
    {
        // [action, actor, subject type, subject id, summary]
        $entries = [
            ['document.approved', 'direccion@example.test', 'Document', '1', 'Aprobada la revisión 0 de la Política Ambiental.'],
            ['nonconformity.opened', 'calidad@example.test', 'NonConformity', '1', 'Abierta la NC.AE.2025.01 tras la auditoría de seguimiento.'],
            ['nonconformity.closed', 'calidad@example.test', 'NonConformity', '1', 'Cerrada la NC.AE.2025.01 con eficacia verificada.'],
            ['consumption.created', 'mantenimiento@example.test', 'ConsumptionReading', null, 'Registrada la lectura de electricidad de enero de 2025.'],
            ['user.login', 'sga@example.test', 'User', null, 'Acceso mediante enlace mágico.'],
        ];

        foreach ($entries as [$action, $actor, $subjectType, $subjectId, $summary]) {
            $manager->persist(new AuditLog($action, $actor, $subjectType, $subjectId, $summary));
        }

        $manager->flush();
    }
}
