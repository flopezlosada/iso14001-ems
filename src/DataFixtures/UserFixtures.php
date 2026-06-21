<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds a development user so the magic-link login can be exercised locally
 * (request a link for this address and open it from Mailpit).
 */
class UserFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setFullName('Usuario de prueba')
            ->setEmail('tester@example.test')
            ->setActive(true);

        $manager->persist($user);
        $manager->flush();
    }
}
