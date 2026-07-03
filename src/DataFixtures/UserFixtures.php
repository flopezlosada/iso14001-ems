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
 *
 * On top of the synthetic backbone, an OPTIONAL local admin is seeded from the
 * LOCAL_SSO_ADMIN_EMAIL env var (set in .env.local, gitignored): it lets a real developer sign in
 * with Google SSO in local (the {@see \App\Security\GoogleAuthenticator} never creates users, so
 * the account must exist beforehand) without committing a real e-mail to the repo. The var is unset
 * in production, so this user is never seeded there — real people are provisioned separately.
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

        // Local-only real developer, seeded only when LOCAL_SSO_ADMIN_EMAIL is set (see class docblock).
        $localAdminEmail = trim((string) ($_ENV['LOCAL_SSO_ADMIN_EMAIL'] ?? ''));
        if ('' !== $localAdminEmail) {
            $localAdmin = (new User())
                ->setFullName('Administrador (local)')
                ->setEmail($localAdminEmail)
                ->setActive(true);
            $localAdmin->addAssignedRole($this->getReference(RoleFixtures::ref('admin'), Role::class));
            $manager->persist($localAdmin);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RoleFixtures::class];
    }
}
