<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\InterestedParty;
use Doctrine\Persistence\ObjectManager;

/**
 * The annual interested-parties register (F.04.0 / PPI "Identificación y Evaluación de Partes
 * Interesadas"). Sample DEMO data modelled on the parties typically identified by an education
 * centre. All values are generic categories (no real personal data, no centre name) — safe for git.
 */
final class InterestedPartyFixtures extends AbstractDemoFixture
{
    /** Reference name for the interested party with the given name, so other fixtures can wire to it. */
    public static function ref(string $name): string
    {
        return 'interested-party-'.$name;
    }

    public function load(ObjectManager $manager): void
    {
        // [review year, party name, needs and expectations, incidents|null]
        $parties = [
            [2025, 'Alumnado',
                'Atención personalizada y cercana, confidencialidad, buena presentación de las '
                .'instalaciones, uso de materiales reciclables y climatización adecuada de las aulas.',
                'NO'],
            [2025, 'Plantilla',
                'Condiciones adecuadas de trabajo, medios y materiales para realizarlo, formación y '
                .'promoción, conciliación, y un entorno respetuoso con el medio ambiente.',
                null],
            [2025, 'Proveedores',
                'Cumplimiento de cláusulas y condiciones, puntualidad en los pagos, buena comunicación '
                .'y relaciones a largo plazo con entidades certificadas en ISO 14001:2015.',
                null],
            [2025, 'Dirección',
                'Velar por la buena imagen del centro, mejorar la eficiencia energética y disponer de '
                .'un centro respetuoso con el medio ambiente.',
                null],
            [2025, 'Administraciones Públicas',
                'Documentación actualizada y accesible, y que las actividades velen por el medio '
                .'ambiente y por la correcta segregación y retirada de residuos según la ley vigente.',
                null],
            [2025, 'Gestores de residuos',
                'Confianza en el centro para gestionar sus residuos y que estos se encuentren '
                .'segregados correctamente para facilitar las retiradas.',
                null],
        ];

        foreach ($parties as [$year, $name, $needs, $incidents]) {
            $party = new InterestedParty();
            $party->setReviewYear($year)
                ->setName($name)
                ->setNeedsAndExpectations($needs)
                ->setIncidents($incidents);
            $manager->persist($party);
            $this->addReference(self::ref($name), $party);
        }

        $manager->flush();
    }
}
