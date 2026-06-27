<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TrainingEvidence;
use Doctrine\Persistence\ObjectManager;

/**
 * The environmental training evidence register ("Registro de evidencias de formación ambiental",
 * ISO 14001:2015 §7.2/§7.3). Sample DEMO data: a few people who received training, with and without
 * a completed questionnaire. All names are obviously fictitious placeholders (no real personal data,
 * no centre name) — safe for git; real evidence is loaded directly in production.
 *
 * Kept as a flat log (no link to the training plan) to mirror the real register, which has only the
 * four columns name/training/date/questionnaire; the optional link to a planned action is exercised
 * in the tests.
 */
final class TrainingEvidenceFixtures extends AbstractDemoFixture
{
    public function load(ObjectManager $manager): void
    {
        // [person name, training description, training date, questionnaire completed]
        $rows = [
            ['Docente de ejemplo 1', 'Sensibilización ambiental ISO 14001', '2025-09-03', true],
            ['Docente de ejemplo 2', 'Sensibilización ambiental ISO 14001', '2025-09-03', false],
            ['Personal de mantenimiento de ejemplo', 'Gestión de residuos y segregación correcta', '2025-02-12', true],
            ['Personal de secretaría de ejemplo', 'Manejo seguro de productos químicos', '2026-04-20', false],
        ];

        foreach ($rows as [$personName, $description, $date, $questionnaire]) {
            $evidence = new TrainingEvidence();
            $evidence->setPersonName($personName)
                ->setTrainingDescription($description)
                ->setTrainingDate(new \DateTimeImmutable($date))
                ->setQuestionnaireCompleted($questionnaire);
            $manager->persist($evidence);
        }

        $manager->flush();
    }
}
