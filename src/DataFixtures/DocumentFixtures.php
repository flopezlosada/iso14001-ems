<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ApprovalEvent;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\VersionStatus;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * The document registry (F.01.0 "Lista de Documentación Vigente"), the heart of the control of
 * documented information (PC.01.0). Part of the GOLDEN backbone.
 *
 * Codes and titles are modelled on the centre's real taxonomy (Política, Manual MA-04.x,
 * procedures PC/PG, forms F.xx, records RG/DO) but every artefact is synthetic. Most documents
 * carry an approved in-force revision 0 with an {@see ApprovalEvent}; a couple are left in
 * DRAFT/IN_REVIEW on purpose so the lifecycle states are exercised.
 */
final class DocumentFixtures extends AbstractGoldenFixture implements DependentFixtureInterface
{
    /** Reference name for the document with the given code, so alerts can wire to it. */
    public static function ref(string $code): string
    {
        return 'document-'.$code;
    }

    public function load(ObjectManager $manager): void
    {
        // [code, title, type, responsible role code, retention years|null, status, owner user key]
        $documents = [
            ['PA.01.0', 'Política Ambiental', DocumentType::POLICY, 'direction', null, VersionStatus::APPROVED, 'direccion'],
            ['MA-04.01.01', 'Manual de Gestión Ambiental', DocumentType::MANUAL, 'ems_manager', null, VersionStatus::APPROVED, 'sga'],
            ['PC.01.0', 'Gestión de la Información Documentada', DocumentType::PROCEDURE, 'ems_manager', null, VersionStatus::APPROVED, 'sga'],
            ['PG-06.01', 'Identificación y Evaluación de Aspectos Ambientales', DocumentType::PROCEDURE, 'ems_manager', null, VersionStatus::APPROVED, 'sga'],
            ['PC.10.0', 'Tratamiento de No Conformidades y Acciones Correctivas', DocumentType::PROCEDURE, 'quality', null, VersionStatus::APPROVED, 'calidad'],
            ['DO-04.02', 'Mapa de Procesos', DocumentType::RECORD, 'ems_manager', 3, VersionStatus::APPROVED, 'sga'],
            ['F.01.0', 'Lista de Documentación Vigente', DocumentType::FORM, 'ems_manager', 3, VersionStatus::APPROVED, 'sga'],
            ['F.08.0', 'Gestión de Riesgos y Oportunidades', DocumentType::FORM, 'ems_manager', 3, VersionStatus::APPROVED, 'sga'],
            ['F.09.0', 'Indicadores Ambientales', DocumentType::FORM, 'ems_manager', 3, VersionStatus::APPROVED, 'sga'],
            ['F.11.0', 'Listado de Control de No Conformidades', DocumentType::FORM, 'quality', 3, VersionStatus::APPROVED, 'calidad'],
            // Left mid-lifecycle on purpose to exercise non-approved states:
            ['F.03.0', 'Plan Anual de Formación', DocumentType::FORM, 'secretary', 3, VersionStatus::IN_REVIEW, 'secretaria'],
            ['RG-07.04.00', 'Comunicaciones Externas e Internas', DocumentType::RECORD, 'ems_manager', 3, VersionStatus::DRAFT, 'sga'],
        ];

        foreach ($documents as [$code, $title, $type, $roleCode, $retention, $status, $ownerKey]) {
            $document = new Document();
            $document->setCode($code)
                ->setTitle($title)
                ->setType($type)
                ->setProcess($this->processFor($type))
                ->setRetentionYears($retention)
                ->setResponsibleRole($this->getReference(RoleFixtures::ref($roleCode), Role::class));

            $version = new DocumentVersion();
            $version->setRevisionNumber(0)
                ->setIssueDate(new \DateTimeImmutable('2024-01-08'))
                ->setStatus($status)
                ->setAuthor($this->getReference(UserFixtures::ref($ownerKey), User::class)->getFullName())
                ->setChangeSummary('Edición inicial.');
            $document->addVersion($version);

            // Only approved revisions carry the tamper-evident approval event (cláusula 7.5).
            if (VersionStatus::APPROVED === $status) {
                $approver = $this->getReference(UserFixtures::ref('direccion'), User::class);
                $event = new ApprovalEvent();
                $event->setApprover($approver)
                    ->setApprovedAt(new \DateTimeImmutable('2024-01-08 10:00:00'))
                    ->setIntegrityHash(hash('sha256', $code.'#0'));
                $version->addApprovalEvent($event);
            }

            $manager->persist($document);
            $this->addReference(self::ref($code), $document);
        }

        $manager->flush();
    }

    /** Provisional free-text process tag derived from the document type (the real map is pending). */
    private function processFor(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::POLICY, DocumentType::MANUAL => 'Estratégico',
            DocumentType::PROCEDURE => 'Apoyo',
            default => 'Operativo',
        };
    }

    public function getDependencies(): array
    {
        return [RoleFixtures::class, UserFixtures::class];
    }
}
