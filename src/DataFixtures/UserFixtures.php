<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * The people who use the system, each holding one or more {@see RoleFixtures} responsibilities.
 *
 * Part of the GOLDEN backbone. All names/e-mails are synthetic (no real personal data): the
 * @example.test addresses make it obvious nothing leaves local. The admin user lets the
 * magic-link login and the admin-only views (e.g. the activity trail) be exercised locally —
 * request a link for its address and open it from Mailpit.
 */
final class UserFixtures extends AbstractGoldenFixture implements DependentFixtureInterface
{
    /** Reference name for the user with the given key, so other fixtures can wire to it. */
    public static function ref(string $key): string
    {
        return 'user-'.$key;
    }

    public function load(ObjectManager $manager): void
    {
        // key => [full name, e-mail, role codes]. The key doubles as the reference suffix.
        $users = [
            'admin' => ['Usuario de prueba', 'tester@example.test', ['admin']],
            'direccion' => ['Marta Director Ejemplo', 'direccion@example.test', ['direction']],
            'sga' => ['Carlos Responsable SGA', 'sga@example.test', ['ems_manager']],
            'calidad' => ['Lucía Calidad Ejemplo', 'calidad@example.test', ['quality']],
            'mantenimiento' => ['Pedro Mantenimiento Ejemplo', 'mantenimiento@example.test', ['maintenance']],
            'secretaria' => ['Ana Secretaría Ejemplo', 'secretaria@example.test', ['secretary']],
        ];

        foreach ($users as $key => [$fullName, $email, $roleCodes]) {
            $user = new User();
            $user->setFullName($fullName)->setEmail($email)->setActive(true);
            foreach ($roleCodes as $code) {
                $user->addAssignedRole($this->getReference(RoleFixtures::ref($code), Role::class));
            }
            $manager->persist($user);
            $this->addReference(self::ref($key), $user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RoleFixtures::class];
    }
}
