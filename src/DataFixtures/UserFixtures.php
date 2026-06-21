<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds a development admin user so the magic-link login and the admin-only views (e.g. the
 * activity trail) can be exercised locally. Request a link for this address and open it from
 * Mailpit.
 */
class UserFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $admin = new Role();
        $admin->setCode('admin')->setName('Administrador');
        $manager->persist($admin);

        $user = new User();
        $user->setFullName('Usuario de prueba')
            ->setEmail('tester@example.test')
            ->setActive(true)
            ->addRole($admin);
        $manager->persist($user);

        $manager->flush();
    }
}
