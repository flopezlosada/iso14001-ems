<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\WasteRecord;
use Doctrine\Persistence\ObjectManager;

/**
 * Waste removals during 2025, modelled on the centre's "Archivo cronológico de RESIDUOS". Sample
 * DEMO data with a realistic mix of hazardous (LER codes marked with *) and non-hazardous waste.
 */
final class WasteRecordFixtures extends AbstractDemoFixture
{
    public function load(ObjectManager $manager): void
    {
        // [LER code, description, kg, pickup date, manager, hazardous]
        $records = [
            ['200101', 'Papel y cartón', '320.000', '2025-02-14', 'Recogida municipal de papel-cartón', false],
            ['150110', 'Envases que contienen restos de sustancias peligrosas', '18.500', '2025-03-20', 'Gestión de Residuos Sierra Norte S.L.', true],
            ['200121', 'Tubos fluorescentes y residuos que contienen mercurio', '12.000', '2025-04-10', 'Gestión de Residuos Sierra Norte S.L.', true],
            ['080318', 'Tóner de impresión distinto del especificado en 080317', '9.300', '2025-05-08', 'Suministros Escolares Madrid (logística inversa)', false],
            ['200133', 'Pilas y acumuladores que contienen sustancias peligrosas', '6.700', '2025-06-12', 'Punto limpio municipal', true],
            ['200136', 'Equipos eléctricos y electrónicos desechados (RAEE no peligrosos)', '85.000', '2025-09-25', 'Gestor autorizado de RAEE', false],
            ['200101', 'Papel y cartón', '290.000', '2025-11-18', 'Recogida municipal de papel-cartón', false],
            ['200121', 'Tubos fluorescentes y residuos que contienen mercurio', '10.500', '2025-12-03', 'Gestión de Residuos Sierra Norte S.L.', true],
        ];

        foreach ($records as [$ler, $description, $kg, $pickup, $manager_, $hazardous]) {
            $record = new WasteRecord();
            $record->setLerCode($ler)
                ->setDescription($description)
                ->setQuantityKg($kg)
                ->setPickupDate(new \DateTimeImmutable($pickup))
                ->setManager($manager_)
                ->setHazardous($hazardous);
            $manager->persist($record);
        }

        $manager->flush();
    }
}
