<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\DafoAnalysis;
use Doctrine\Persistence\ObjectManager;

/**
 * SWOT (DAFO) analyses of the centre's environmental context (register "F.06.0"). Part of the
 * GOLDEN backbone so the context module has something to show.
 *
 * The contents are synthetic, generic statements (no real centre data); two school years are
 * seeded so the list ordering (most recent first) has something to exercise.
 */
final class DafoAnalysisFixtures extends AbstractGoldenFixture
{
    public function load(ObjectManager $manager): void
    {
        // [school year, weaknesses, threats, strengths, opportunities]
        /** @var list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string}> $analyses */
        $analyses = [
            [
                '2024-2025',
                "Falta de formación ambiental de parte del personal.\nFinanciación dependiente de la administración.\nDecisiones lentas por la carga burocrática.",
                null, // un ejercicio anterior con un cuadrante sin rellenar: forma de dato realista
                "Personal cualificado en materia ambiental.\nInstalaciones mantenidas y cuidadas.\nImplicación de la dirección y del profesorado.",
                "Prestigio derivado de la certificación.\nParticipación en proyectos ambientales.\nConcienciación de la comunidad educativa.",
            ],
            [
                '2025-2026',
                "Conocimientos ambientales desiguales entre el personal.\nFinanciación dependiente de la administración.\nRigidez del plan de estudios frente a la innovación.",
                "Nuevos problemas derivados del cambio climático.\nIncremento de la factura energética.\nCompetencia con otros centros por el talento.",
                "Recursos humanos cualificados.\nEquipos adecuados para actividades respetuosas con el medio ambiente.\nDeseo de mejora continua del centro.",
                "Reconocimiento y prestigio por la certificación.\nColaboración con empresas comprometidas con el medio ambiente.\nMejora de la gestión de los residuos peligrosos.",
            ],
        ];

        foreach ($analyses as [$schoolYear, $weaknesses, $threats, $strengths, $opportunities]) {
            $analysis = new DafoAnalysis();
            $analysis->setSchoolYear($schoolYear)
                ->setWeaknesses($weaknesses)
                ->setThreats($threats)
                ->setStrengths($strengths)
                ->setOpportunities($opportunities);
            $manager->persist($analysis);
        }

        $manager->flush();
    }
}
