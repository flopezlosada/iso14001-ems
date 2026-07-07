<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Supplier;
use App\Entity\SupplierEvaluation;
use App\Entity\SupplierIncident;
use App\Enum\SupplierCriterion;
use Doctrine\Persistence\ObjectManager;

/**
 * Suppliers relevant to the environmental management system, with their yearly capability
 * evaluations and incidents (PC.05.0, F.12.0). Sample DEMO data; all company names are synthetic.
 */
final class SupplierFixtures extends AbstractDemoFixture
{
    public function load(ObjectManager $manager): void
    {
        $suppliers = [
            [
                'name' => 'Gestión de Residuos Sierra Norte S.L.',
                'service' => 'Gestor autorizado de residuos peligrosos',
                'notes' => 'Recoge fluorescentes, envases contaminados y pilas.',
                'evaluations' => [[2024, SupplierCriterion::CAPABLE], [2025, SupplierCriterion::CAPABLE], [2026, SupplierCriterion::CAPABLE]],
                'incidents' => [],
            ],
            [
                'name' => 'Calefacción y Clima del Valle',
                'service' => 'Mantenimiento de la caldera de calefacción',
                'notes' => null,
                'evaluations' => [[2024, SupplierCriterion::CAPABLE], [2025, SupplierCriterion::ON_TRIAL], [2026, SupplierCriterion::ON_TRIAL]],
                'incidents' => [
                    ['2025-01-18', 'Retraso de dos semanas en la revisión anual de la caldera.', 'Se reprogramó la visita y se reforzó el contrato de mantenimiento.', true],
                ],
            ],
            [
                'name' => 'Suministros Escolares Madrid',
                'service' => 'Material de oficina, papel y tóner',
                'notes' => 'Ofrece logística inversa para tóner usado.',
                'evaluations' => [[2025, SupplierCriterion::CAPABLE]],
                'incidents' => [],
            ],
            [
                'name' => 'Limpiezas Buitrago',
                'service' => 'Servicio de limpieza de instalaciones',
                'notes' => 'Uso de productos de limpieza con etiqueta ecológica pendiente de confirmar.',
                'evaluations' => [[2024, SupplierCriterion::ON_TRIAL], [2025, SupplierCriterion::NOT_CAPABLE], [2026, SupplierCriterion::NOT_CAPABLE]],
                'incidents' => [
                    ['2025-03-05', 'Uso de productos no autorizados sin ficha de datos de seguridad.', null, false],
                ],
            ],
        ];

        foreach ($suppliers as $data) {
            $supplier = new Supplier();
            $supplier->setName($data['name'])
                ->setProductOrService($data['service'])
                ->setNotes($data['notes']);
            $manager->persist($supplier);

            foreach ($data['evaluations'] as [$year, $criterion]) {
                $evaluation = new SupplierEvaluation();
                $evaluation->setSupplier($supplier)->setYear($year)->setCriterion($criterion);
                $manager->persist($evaluation);
            }

            foreach ($data['incidents'] as [$date, $description, $resolution, $severe]) {
                $incident = new SupplierIncident();
                $incident->setSupplier($supplier)
                    ->setOccurredOn(new \DateTimeImmutable($date))
                    ->setDescription($description)
                    ->setResolution($resolution)
                    ->setSevere($severe);
                $manager->persist($incident);
            }
        }

        $manager->flush();
    }
}
