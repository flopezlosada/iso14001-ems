<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ProcessArea;
use Doctrine\Persistence\ObjectManager;

/**
 * The process/area map of the management system (DO-04.02), used to classify risks and
 * opportunities (F.08.0). Part of the GOLDEN backbone.
 */
final class ProcessAreaFixtures extends AbstractGoldenFixture
{
    /** Reference name for the process area with the given key, so risks can wire to it. */
    public static function ref(string $key): string
    {
        return 'process-area-'.$key;
    }

    public function load(ObjectManager $manager): void
    {
        // key => display name
        $areas = [
            'direccion' => 'Dirección y planificación',
            'ensenanza' => 'Procesos de enseñanza-aprendizaje',
            'compras' => 'Compras y proveedores',
            'mantenimiento' => 'Mantenimiento e instalaciones',
            'ambiental' => 'Gestión ambiental',
        ];

        foreach ($areas as $key => $name) {
            $area = new ProcessArea();
            $area->setName($name)->setActive(true);
            $manager->persist($area);
            $this->addReference(self::ref($key), $area);
        }

        $manager->flush();
    }
}
