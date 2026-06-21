<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ApprovalEvent;
use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use App\Enum\VersionStatus;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * The obligations backbone: the ~34 periodic obligations of the centre's "RELACIÓN DE DOCUMENTOS A
 * CUMPLIMENTAR ISO 14001", plus a few framework documents (manual, procedures, the document
 * register itself) that are not periodic obligations. Part of the GOLDEN backbone.
 *
 * Each obligation carries its ISO chapter (supra-structure), the responsible role, the module it
 * is filled in (linked Area, or null = "pending module"), a manual status with its nuance note,
 * and the plain-language instructions that replace the (now gone) consultant's guidance. The
 * review cadence lives in {@see ScheduledAlertFixtures}.
 *
 * Codes and titles mirror the centre's real taxonomy but every artefact is synthetic (no PII):
 * statuses and notes are plausible samples that exercise the lifecycle and the "Qué toca" view,
 * NOT the centre's real review dates.
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
        foreach ($this->obligations() as $row) {
            $this->createDocument($manager, $row);
        }
        foreach ($this->frameworkDocuments() as $row) {
            $this->createDocument($manager, $row);
        }

        $manager->flush();
    }

    /**
     * The 34 periodic obligations of the register, grouped by PDCA phase / ISO chapter.
     *
     * @return array<int, array{code: string, title: string, type: DocumentType, chapter: IsoChapter, role: string, area: ?Area, status: ObligationStatus, note: ?string, instructions: string}>
     */
    private function obligations(): array
    {
        return [
            // 00.PLAN — Contexto (cap. 4)
            ['code' => 'DO-04.02', 'title' => 'Mapa de Procesos', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::CONTEXT, 'role' => 'direction', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Rev 01 (23-26).', 'instructions' => 'Refleja los procesos estratégicos, de soporte y operativos del centro. Revisa que sigan vigentes y actualiza fecha y nº de revisión en el pie de página.'],
            ['code' => 'F.06.0', 'title' => 'DAFO', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::CONTEXT, 'role' => 'direction', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Rev 01 (2025).', 'instructions' => 'Identifica Debilidades, Amenazas, Fortalezas y Oportunidades con enfoque ambiental. Cuanto más fiel sea a la entidad, mejor. Revisa y actualiza fecha y nº de revisión.'],
            ['code' => 'F.04.0', 'title' => 'Identificación y Evaluación de Partes Interesadas (PPI)', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::CONTEXT, 'role' => 'direction', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Hecho 2025.', 'instructions' => 'Identifica las partes interesadas (internas y externas) y sus necesidades y expectativas. Indica cualquier incidencia que se haya tenido con ellas.'],
            // 00.PLAN — Liderazgo (cap. 5)
            ['code' => 'PA.01.0', 'title' => 'Política Ambiental', 'type' => DocumentType::POLICY, 'chapter' => IsoChapter::LEADERSHIP, 'role' => 'direction', 'area' => null, 'status' => ObligationStatus::IN_REVIEW, 'note' => 'Hecho (23-26); pendiente de firma de dirección.', 'instructions' => 'Indica el alcance del sistema y la ubicación de las instalaciones, difúndela entre las partes interesadas y el personal, publícala (tablón/web) y envíala a proveedores. Recoge la firma de dirección.'],
            ['code' => 'ORG-05.01', 'title' => 'Organigrama ISO 14001', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::LEADERSHIP, 'role' => 'direction', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Hecho (25-26).', 'instructions' => 'Cuelga el organigrama del centro reflejando los puestos de trabajo, funciones y competencias de cada puesto.'],
            // 00.PLAN — Planificación (cap. 6)
            ['code' => 'F.07.01', 'title' => 'Listado de Objetivos Generales', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::PLANNING, 'role' => 'ems_manager', 'area' => Area::OBJECTIVE, 'status' => ObligationStatus::DONE, 'note' => 'Hecho (25-26).', 'instructions' => 'Añade todos los objetivos ambientales del año (p. ej. reducir el consumo eléctrico un 5%). Revisión semestral; ten en cuenta los no terminados del año anterior.'],
            ['code' => 'F.07.0', 'title' => 'Ficha de Objetivo', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::PLANNING, 'role' => 'ems_manager', 'area' => Area::OBJECTIVE, 'status' => ObligationStatus::DONE, 'note' => 'Hecho (25-26). Una ficha por objetivo.', 'instructions' => 'Crea una ficha por cada objetivo ambiental y revisa semestralmente su grado de cumplimiento.'],
            ['code' => 'RL-06.03', 'title' => 'Requisitos Legales', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::PLANNING, 'role' => 'ems_manager', 'area' => Area::LEGAL_REQUIREMENT, 'status' => ObligationStatus::PENDING, 'note' => 'Pendiente de extracción de los requisitos de cada ley.', 'instructions' => 'Revisa los requisitos legales aplicables a la actividad y extrae las obligaciones concretas de cada norma. Evaluación semestral del cumplimiento.'],
            ['code' => 'RG-06.01.01', 'title' => 'Evaluación de Aspectos Ambientales', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::PLANNING, 'role' => 'ems_manager', 'area' => Area::ASPECT, 'status' => ObligationStatus::DONE, 'note' => 'Hecho; NC corregida.', 'instructions' => 'Valora frecuencia, intensidad y peligrosidad de cada aspecto, indica si es significativo y añade observaciones. Revisa cambios en los impactos y actualiza revisión y fecha en cabecera.'],
            ['code' => 'F-6.1.2', 'title' => 'Consumos (luz, agua, gasoil, papel, tóner)', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::PLANNING, 'role' => 'secretary', 'area' => Area::CONSUMPTION, 'status' => ObligationStatus::DONE, 'note' => 'Hecho 2026.', 'instructions' => 'Registra mensualmente los consumos de cada suministro. El objetivo es estudiar si el consumo mejora con el tiempo.'],
            ['code' => 'F-6.1.3', 'title' => 'Consumo de Residuos', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::PLANNING, 'role' => 'secretary', 'area' => Area::WASTE, 'status' => ObligationStatus::DONE, 'note' => 'Hecho 2026.', 'instructions' => 'Registra mensualmente los residuos generados, cuantificados a partir del archivo cronológico de residuos.'],
            ['code' => 'F.08.0', 'title' => 'Gestión de Riesgos y Oportunidades', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::PLANNING, 'role' => 'direction', 'area' => Area::RISK_OPPORTUNITY, 'status' => ObligationStatus::IN_REVIEW, 'note' => 'Falta fecha de dirección; revisar acciones e ítems.', 'instructions' => 'A partir del DAFO, identifica riesgos (debilidades/amenazas) y oportunidades (fortalezas/oportunidades), evalúa Probabilidad x Impacto y define planes de acción para los de relevancia alta o moderada. Planificación anual, revisión semestral.'],
            // 01.IMPLEMENTACIÓN — Apoyo (cap. 7)
            ['code' => 'RG-07.04.00', 'title' => 'Comunicaciones Externas e Internas', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::SUPPORT, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Revisado 2025.', 'instructions' => 'Adjunta evidencias de las comunicaciones internas y externas (correos, etc.). Revisa y actualiza fecha y nº de revisión.'],
            ['code' => 'RG-07.01.01', 'title' => 'Listado de Equipos y Mantenimiento', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::SUPPORT, 'role' => 'cfpg', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Rev 01 (26/02/2026).', 'instructions' => 'Refleja infraestructuras, maquinaria, vehículos y equipos electrónicos. Añade las fechas de mantenimiento, calibración e ITV.'],
            ['code' => 'F.03.0', 'title' => 'Plan Anual de Formación', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::SUPPORT, 'role' => 'ems_manager', 'area' => Area::TRAINING, 'status' => ObligationStatus::IN_REVIEW, 'note' => 'Revisado 2026. El plan se elabora en junio.', 'instructions' => 'Indica las formaciones (medio ambiente, PRL, etc.) realizadas y las previstas para el año. Crea el test de evaluación de la formación.'],
            ['code' => 'F.0X.0', 'title' => 'RRHH', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::SUPPORT, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Revisado Sept. 2025.', 'instructions' => 'Rellena los datos de los trabajadores, las competencias y funciones por puesto y enlaza el organigrama. Revisa si ha habido cambios.'],
            ['code' => 'RG-07.02', 'title' => 'Registro de Evidencias de Formación', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::SUPPORT, 'role' => 'ems_manager', 'area' => Area::TRAINING, 'status' => ObligationStatus::NOT_APPLICABLE, 'note' => 'No aplica hasta sept. 2026 (nueva creación).', 'instructions' => 'Recoge las evidencias de la formación ambiental impartida. Se crea junto al plan de formación de junio.'],
            // 01.IMPLEMENTACIÓN — Operación (cap. 8)
            ['code' => 'OCA-08.01', 'title' => 'OCAs Externas (revisiones de mantenimiento)', 'type' => DocumentType::EXTERNAL_EVIDENCE, 'chapter' => IsoChapter::OPERATION, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Revisada marzo 2026.', 'instructions' => 'Sube las revisiones de OCA externas: ascensores, RITE, PCI, climatización, depósito de combustible, baja/media/alta tensión, legionella, etc., según proceda.'],
            ['code' => 'RG-08.01.01', 'title' => 'Control Operacional (interno)', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::OPERATION, 'role' => 'cleaning', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Revisada marzo 2026.', 'instructions' => 'Checklist mensual: marca con una X si cada ítem (consumos, instalaciones) se encuentra conforme o no conforme.'],
            ['code' => 'RG-08.02.01-I', 'title' => 'Informe de Simulacro — Incendio', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::OPERATION, 'role' => 'ems_manager', 'area' => Area::EMERGENCY, 'status' => ObligationStatus::DONE, 'note' => 'Rev 03 (dic. 2025).', 'instructions' => 'Realiza el informe del simulacro de incendio: pautas de actuación, fecha, resultado y gestión de los residuos generados. Actualiza fecha y nº de revisión.'],
            ['code' => 'RG-08.02.01-C', 'title' => 'Informe de Simulacro — Caldera', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::OPERATION, 'role' => 'ems_manager', 'area' => Area::EMERGENCY, 'status' => ObligationStatus::DONE, 'note' => 'Rev 03 (feb. 2026).', 'instructions' => 'Realiza el informe del simulacro de caldera: pautas de actuación, fecha y resultado. Actualiza fecha y nº de revisión.'],
            ['code' => 'FS-08.03', 'title' => 'Fichas de Seguridad de Productos Químicos', 'type' => DocumentType::EXTERNAL_EVIDENCE, 'chapter' => IsoChapter::OPERATION, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Revisado 2025.', 'instructions' => 'Cada vez que se compre un producto químico nuevo, descarga o solicita su ficha de seguridad. Pídelas anualmente a la contrata de limpieza.'],
            ['code' => 'RG-08.04', 'title' => 'Control de Mantenimiento de Maquinaria', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::OPERATION, 'role' => 'cfpg', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Revisado Sept. 2025.', 'instructions' => 'Actualiza las fechas de mantenimiento de la maquinaria de jardinería en el documento.'],
            ['code' => 'RG-08.05', 'title' => 'Descripción de Máquinas de Jardinería', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::OPERATION, 'role' => 'cfpg', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Revisado 2025.', 'instructions' => 'Incluye la maquinaria nueva y su descripción.'],
            ['code' => 'F.12.0', 'title' => 'Control de Proveedores', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::OPERATION, 'role' => 'ems_manager', 'area' => Area::SUPPLIER, 'status' => ObligationStatus::DONE, 'note' => 'Revisado feb. 2026.', 'instructions' => 'Añade los proveedores nuevos y mantén actualizado el control de proveedores.'],
            ['code' => 'F.12.1', 'title' => 'Comunicación a Proveedores', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::OPERATION, 'role' => 'ems_manager', 'area' => Area::SUPPLIER, 'status' => ObligationStatus::NOT_APPLICABLE, 'note' => 'No procede actualmente.', 'instructions' => 'Envía a los proveedores la carta de comunicación ISO 14001 y solicita que la devuelvan rellena junto con sus certificados ISO.'],
            ['code' => 'RG-08.06', 'title' => 'Archivo Cronológico de Residuos', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::OPERATION, 'role' => 'ems_manager', 'area' => Area::WASTE, 'status' => ObligationStatus::DONE, 'note' => 'Revisada abril 2026.', 'instructions' => 'En cada retirada, añade la cantidad y el gestor que se lleva los residuos.'],
            // 02.VERIFICAR — Evaluación del desempeño (cap. 9)
            ['code' => 'RG-09.03.01', 'title' => 'Revisión por la Dirección', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::PERFORMANCE_EVALUATION, 'role' => 'direction', 'area' => null, 'status' => ObligationStatus::IN_REVIEW, 'note' => 'En revisión 2026; falta firma de dirección.', 'instructions' => 'Elabora el informe de revisión por la dirección sobre los requisitos y puntos de la norma. Actualiza revisión, fecha y firma.'],
            ['code' => 'F.09.0', 'title' => 'Indicadores', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::PERFORMANCE_EVALUATION, 'role' => 'ems_manager', 'area' => Area::INDICATOR, 'status' => ObligationStatus::DONE, 'note' => 'Revisado hasta marzo 2026.', 'instructions' => 'Define indicadores (consumos, etc.) con un valor límite de referencia y estudia mensualmente si todo se encuentra dentro de dicho parámetro.'],
            ['code' => 'AUD-09.02', 'title' => 'Auditoría Externa (certificación)', 'type' => DocumentType::EXTERNAL_EVIDENCE, 'chapter' => IsoChapter::PERFORMANCE_EVALUATION, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::PENDING, 'note' => 'Anual; la realiza un organismo certificador externo en fecha conocida.', 'instructions' => 'Prepara la documentación para la auditoría externa anual de certificación. La fecha de la visita ancla los avisos de preparación: llega con todo al día.'],
            // 03.MEJORA — Mejora (cap. 10)
            ['code' => 'RG-08.07.01', 'title' => 'Plan de Acciones Correctivas', 'type' => DocumentType::RECORD, 'chapter' => IsoChapter::IMPROVEMENT, 'role' => 'ems_manager', 'area' => Area::NONCONFORMITY, 'status' => ObligationStatus::DONE, 'note' => 'A partir del informe del auditor (feb. 2024).', 'instructions' => 'Tras la auditoría externa, define y planifica las acciones correctivas derivadas del informe.'],
            ['code' => 'F.11.0', 'title' => 'Listado de Control de No Conformidades', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::IMPROVEMENT, 'role' => 'ems_manager', 'area' => Area::NONCONFORMITY, 'status' => ObligationStatus::DONE, 'note' => 'Revisada abril 2026.', 'instructions' => 'Registra todas las no conformidades detectadas (auditorías internas y externas) y su seguimiento. Revisión mensual.'],
            ['code' => 'F.10.0', 'title' => 'Informe de No Conformidad y Acciones Correctivas', 'type' => DocumentType::FORM, 'chapter' => IsoChapter::IMPROVEMENT, 'role' => 'ems_manager', 'area' => Area::NONCONFORMITY, 'status' => ObligationStatus::DONE, 'note' => 'Todas cumplidas.', 'instructions' => 'Crea una ficha por cada no conformidad con su causa raíz, acción correctiva, responsable y cierre.'],
        ];
    }

    /**
     * Framework documents that are NOT periodic obligations: the manual, key procedures and the
     * document register itself. They give the registry its backbone of procedures/policies but
     * carry no ISO chapter nor review cadence.
     *
     * @return array<int, array{code: string, title: string, type: DocumentType, chapter: null, role: string, area: ?Area, status: ObligationStatus, note: ?string, instructions: ?string}>
     */
    private function frameworkDocuments(): array
    {
        return [
            ['code' => 'MA-04.01.01', 'title' => 'Manual de Gestión Ambiental', 'type' => DocumentType::MANUAL, 'chapter' => null, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => null, 'instructions' => null],
            ['code' => 'PC.01.0', 'title' => 'Gestión de la Información Documentada', 'type' => DocumentType::PROCEDURE, 'chapter' => null, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => null, 'instructions' => null],
            ['code' => 'PC.10.0', 'title' => 'Tratamiento de No Conformidades y Acciones Correctivas', 'type' => DocumentType::PROCEDURE, 'chapter' => null, 'role' => 'quality', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => null, 'instructions' => null],
            ['code' => 'PG-06.01', 'title' => 'Identificación y Evaluación de Aspectos Ambientales', 'type' => DocumentType::PROCEDURE, 'chapter' => null, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => null, 'instructions' => null],
            ['code' => 'F.01.0', 'title' => 'Lista de Documentación Vigente', 'type' => DocumentType::FORM, 'chapter' => null, 'role' => 'ems_manager', 'area' => null, 'status' => ObligationStatus::DONE, 'note' => 'Es el propio registro documental que se enseña al auditor; se mantiene al día desde el catálogo.', 'instructions' => null],
        ];
    }

    /**
     * Persists one document with an initial revision 0 whose lifecycle state mirrors the obligation
     * status, and a tamper-evident approval event for approved revisions (cláusula 7.5).
     *
     * @param array{code: string, title: string, type: DocumentType, chapter: ?IsoChapter, role: string, area: ?Area, status: ObligationStatus, note: ?string, instructions: ?string} $row
     */
    private function createDocument(ObjectManager $manager, array $row): void
    {
        $retention = \in_array($row['type'], [DocumentType::POLICY, DocumentType::MANUAL, DocumentType::PROCEDURE], true) ? null : 3;

        $document = new Document();
        $document->setCode($row['code'])
            ->setTitle($row['title'])
            ->setType($row['type'])
            ->setIsoChapter($row['chapter'])
            ->setLinkedArea($row['area'])
            ->setStatus($row['status'])
            ->setStatusNote($row['note'])
            ->setInstructions($row['instructions'])
            ->setProcess($this->processFor($row['type']))
            ->setRetentionYears($retention)
            ->setResponsibleRole($this->getReference(RoleFixtures::ref($row['role']), Role::class));

        $versionStatus = $this->versionStatusFor($row['status']);
        $author = $this->getReference(UserFixtures::ref($this->ownerFor($row['role'])), User::class);

        $version = new DocumentVersion();
        $version->setRevisionNumber(0)
            ->setIssueDate(new \DateTimeImmutable('2024-01-08'))
            ->setStatus($versionStatus)
            ->setAuthor($author->getFullName())
            ->setChangeSummary('Edición inicial.');
        $document->addVersion($version);

        if (VersionStatus::APPROVED === $versionStatus) {
            $approver = $this->getReference(UserFixtures::ref('direccion'), User::class);
            $event = new ApprovalEvent();
            $event->setApprover($approver)
                ->setApprovedAt(new \DateTimeImmutable('2024-01-08 10:00:00'))
                ->setIntegrityHash(hash('sha256', $row['code'].'#0'));
            $version->addApprovalEvent($event);
        }

        $manager->persist($document);
        $this->addReference(self::ref($row['code']), $document);
    }

    /** The initial revision lifecycle state that matches the obligation's manual status. */
    private function versionStatusFor(ObligationStatus $status): VersionStatus
    {
        return match ($status) {
            ObligationStatus::DONE, ObligationStatus::NOT_APPLICABLE => VersionStatus::APPROVED,
            ObligationStatus::IN_REVIEW => VersionStatus::IN_REVIEW,
            ObligationStatus::PENDING => VersionStatus::DRAFT,
        };
    }

    /** The seeded user key that authors documents owned by the given role. */
    private function ownerFor(string $roleCode): string
    {
        return match ($roleCode) {
            'direction' => 'direccion',
            'quality' => 'calidad',
            'secretary' => 'secretaria',
            'maintenance' => 'mantenimiento',
            // cfpg/cleaning have no seeded user yet: the SGA manager authors on their behalf.
            default => 'sga',
        };
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
