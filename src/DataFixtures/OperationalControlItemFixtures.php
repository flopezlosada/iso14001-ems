<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\OperationalControlItem;
use App\Enum\OperationalControlSection;
use Doctrine\Persistence\ObjectManager;

/**
 * The catalogue of operational-control checklist items (PG-08.01 / RG-08.01.01). Part of the GOLDEN
 * backbone: the items are structural (they define the inspection), not sample data. Synthetic but
 * faithful to the centre's internal checklist, grouped by section.
 */
final class OperationalControlItemFixtures extends AbstractGoldenFixture
{
    public function load(ObjectManager $manager): void
    {
        $position = 0;
        foreach ($this->catalog() as [$section, $label]) {
            $manager->persist(
                (new OperationalControlItem())
                    ->setSection($section)
                    ->setLabel($label)
                    ->setPosition($position++)
                    ->setActive(true),
            );
        }

        $manager->flush();
    }

    /**
     * The checklist items in display order (section then item).
     *
     * @return list<array{0: OperationalControlSection, 1: string}> [section, label] pairs
     */
    private function catalog(): array
    {
        return [
            [OperationalControlSection::WATER, 'Grifos y cisternas en buen estado'],
            [OperationalControlSection::WATER, 'Grifos bien cerrados tras su uso'],
            [OperationalControlSection::WATER, 'Uso de la cisterna con criterio'],
            [OperationalControlSection::ENERGY, 'Sistema de ahorro de energía activado'],
            [OperationalControlSection::ENERGY, 'Climatización regulada entre 22 y 24º'],
            [OperationalControlSection::ENERGY, 'Equipos apagados al final de la jornada'],
            [OperationalControlSection::ENERGY, 'Fotocopiadora en modo ahorro'],
            [OperationalControlSection::ENERGY, 'Salvapantallas "Black Screen" activado'],
            [OperationalControlSection::PAPER, 'Fotocopias e impresiones a doble cara'],
            [OperationalControlSection::PAPER, 'Documentos gestionados en formato digital'],
            [OperationalControlSection::PAPER, 'Reutilización de hojas usadas por una cara'],
            [OperationalControlSection::INK, 'Se imprime solo cuando es necesario'],
            [OperationalControlSection::INK, 'Impresión en blanco y negro salvo excepciones'],
            [OperationalControlSection::INK, 'Impresión en modo borrador/rápido'],
            [OperationalControlSection::DISCHARGE, 'No se vierten sustancias peligrosas por el desagüe'],
            [OperationalControlSection::EMISSIONS, 'No se detecta ruido molesto al exterior'],
            [OperationalControlSection::EMISSIONS, 'Registro de focos de emisión cumplimentado'],
            [OperationalControlSection::WEEE, 'Aparatos eléctricos y electrónicos a gestor autorizado'],
            [OperationalControlSection::OFFICE_WASTE, 'Residuos sólidos segregados en sus contenedores'],
            [OperationalControlSection::OFFICE_WASTE, 'Tóner devuelto al proveedor con certificado'],
        ];
    }
}
