<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\EmergencyDrill;
use Doctrine\Persistence\ObjectManager;

/**
 * Emergency-preparedness drills (PG-08.02). Sample DEMO data covering an evacuation drill and a
 * chemical-spill response drill.
 */
final class EmergencyDrillFixtures extends AbstractDemoFixture
{
    public function load(ObjectManager $manager): void
    {
        // [date, emergency type, location, participants, action procedure, conclusions, reported by]
        $drills = [
            ['2025-11-20', 'Incendio y evacuación', 'Edificio principal',
                'Todo el alumnado y personal del centro (≈ 480 personas).',
                'Activación de la alarma, evacuación por las salidas señalizadas y recuento en el punto de encuentro del patio.',
                'Evacuación completada en 4 min 30 s. Se detecta congestión en la escalera este; se propone señalización adicional.',
                'Carlos Responsable SGA'],
            ['2025-04-15', 'Derrame de productos químicos', 'Laboratorio de Ciencias',
                'Profesorado de Ciencias y equipo de mantenimiento.',
                'Aislamiento de la zona, uso del kit de absorción y ventilación del laboratorio según el procedimiento.',
                'Respuesta adecuada. Se repone el material del kit de derrames y se programa formación específica.',
                'Lucía Calidad Ejemplo'],
        ];

        foreach ($drills as [$date, $type, $location, $participants, $procedure, $conclusions, $reportedBy]) {
            $drill = new EmergencyDrill();
            $drill->setDrillDate(new \DateTimeImmutable($date))
                ->setEmergencyType($type)
                ->setLocation($location)
                ->setParticipants($participants)
                ->setActionProcedure($procedure)
                ->setConclusions($conclusions)
                ->setReportedBy($reportedBy);
            $manager->persist($drill);
        }

        $manager->flush();
    }
}
