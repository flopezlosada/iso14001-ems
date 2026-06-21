<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TrainingAction;
use App\Enum\TrainingType;
use Doctrine\Persistence\ObjectManager;

/**
 * The annual training plan (F.03.0 "Plan Anual de Formación"). Sample DEMO data: some actions are
 * already delivered (with actual date and efficacy evaluation) and some are only planned.
 */
final class TrainingActionFixtures extends AbstractDemoFixture
{
    public function load(ObjectManager $manager): void
    {
        // [plan year, description, type, audience, objectives, planned date, methodology,
        //  actual date|null, efficacy evaluation|null]
        $actions = [
            [2025, 'Gestión de residuos y segregación correcta', TrainingType::INTERNAL,
                'Personal de mantenimiento y conserjería',
                'Conocer la clasificación LER y los puntos de recogida del centro.',
                '2025-02-12', 'Sesión presencial de 2 horas con demostración práctica.',
                '2025-02-12', 'Eficaz: se observa mejora en la segregación tras la formación.'],
            [2025, 'Sensibilización ambiental ISO 14001', TrainingType::INTERNAL,
                'Todo el profesorado',
                'Difundir la política ambiental y los aspectos significativos del centro.',
                '2025-09-03', 'Charla en el claustro de inicio de curso.',
                '2025-09-03', 'Eficaz.'],
            [2026, 'Manejo seguro de productos químicos en laboratorios', TrainingType::EXTERNAL,
                'Profesorado de Ciencias y FP',
                'Aplicar las fichas de datos de seguridad y prevenir derrames.',
                '2026-04-20', 'Curso externo acreditado de 8 horas.',
                null, null],
        ];

        foreach ($actions as [$year, $description, $type, $audience, $objectives, $planned, $methodology, $actual, $efficacy]) {
            $action = new TrainingAction();
            $action->setPlanYear($year)
                ->setDescription($description)
                ->setType($type)
                ->setTargetAudience($audience)
                ->setObjectives($objectives)
                ->setPlannedDate(new \DateTimeImmutable($planned))
                ->setMethodology($methodology)
                ->setActualDate(null !== $actual ? new \DateTimeImmutable($actual) : null)
                ->setEfficacyEvaluation($efficacy);
            $manager->persist($action);
        }

        $manager->flush();
    }
}
