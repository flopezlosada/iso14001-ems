<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\EnvironmentalAspect;
use App\Entity\Objective;
use App\Entity\User;
use App\Enum\ObjectiveStatus;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * The environmental objectives and targets (PG-06.04). DEMO layer only: the real objectives are
 * loaded by the ETL ('objectives' importer), so seeding them here as part of the GOLDEN backbone
 * would duplicate them against the real data.
 *
 * Each objective is tied to a responsible person and, where it applies, to the significant
 * aspect that motivates it, so the planning views show realistic links.
 */
final class ObjectiveFixtures extends AbstractDemoFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // [reference, school year, description, responsible user key, related aspect key|null, status, notes|null]
        $objectives = [
            // Previous course (2024-2025): seeds the "copy from previous course" button on 2025-2026.
            ['OBJ.01', '2024-2025', 'Reducir el consumo de agua mediante revisión de la red y grifería.', 'mantenimiento', null, ObjectiveStatus::ACHIEVED, 'Cerrado el curso anterior.'],
            // Current course (2025-2026).
            ['OBJ.02', '2025-2026', 'Reducir el consumo de energía eléctrica un 5% respecto al curso anterior.', 'mantenimiento', 'electricidad', ObjectiveStatus::IN_PROGRESS, 'Sustitución progresiva de luminarias por LED.'],
            ['OBJ.03', '2025-2026', 'Mejorar la segregación de residuos peligrosos en talleres y laboratorios.', 'calidad', 'residuos-peligrosos', ObjectiveStatus::ACHIEVED, 'Nuevos contenedores señalizados instalados.'],
            ['OBJ.04', '2025-2026', 'Realizar una campaña anual de concienciación ambiental con el alumnado.', 'secretaria', 'concienciacion-alumnado', ObjectiveStatus::IN_PROGRESS, null],
            ['OBJ.05', '2025-2026', 'Reducir el consumo de agua mediante revisión de la red y grifería.', 'mantenimiento', null, ObjectiveStatus::NOT_ACHIEVED, 'No se alcanzó el ahorro previsto; se replantea para el próximo curso.'],
        ];

        $sequence = 1;
        foreach ($objectives as [$reference, $schoolYear, $description, $userKey, $aspectKey, $status, $notes]) {
            $objective = new Objective();
            $objective->setReference($reference)
                ->setSequence($sequence++)
                ->setSchoolYear($schoolYear)
                ->setDescription($description)
                ->setResponsible($this->getReference(UserFixtures::ref($userKey), User::class))
                ->setTargetPeriod(sprintf('Curso %s', $schoolYear))
                ->setStatus($status)
                ->setNotes($notes)
                ->setLastReviewedOn(new \DateTimeImmutable('2026-02-01'));

            if (null !== $aspectKey) {
                $objective->setRelatedAspect($this->getReference(EnvironmentalAspectFixtures::ref($aspectKey), EnvironmentalAspect::class));
            }

            $manager->persist($objective);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, EnvironmentalAspectFixtures::class];
    }
}
