<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Settings;
use Doctrine\Persistence\ObjectManager;

/**
 * The singleton {@see Settings} row. Part of the GOLDEN backbone: the entity defaults already match
 * the procedure's baseline (PG-06.01 thresholds and the auto-intensity bounds), so seeding it is
 * just persisting one instance.
 */
final class SettingsFixtures extends AbstractGoldenFixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->persist(new Settings());
        $manager->flush();
    }
}
