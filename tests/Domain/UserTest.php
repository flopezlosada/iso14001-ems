<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Role;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Security-role derivation: ROLE_ADMIN comes from the explicit {@see Role::isAdmin()} flag, not
 * from a role's code. Guards the regression where naming a role "admin" silently granted superuser.
 */
final class UserTest extends TestCase
{
    public function testWithoutRolesOnlyHasRoleUser(): void
    {
        self::assertSame(['ROLE_USER'], (new User())->getRoles());
    }

    public function testAdminFlagGrantsRoleAdmin(): void
    {
        $user = (new User())->addAssignedRole((new Role())->setCode('whatever')->setAdmin(true));

        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testRoleCodedAdminWithoutFlagIsNotAdmin(): void
    {
        // The whole point of the change: the code is no longer magic.
        $user = (new User())->addAssignedRole((new Role())->setCode('admin')->setAdmin(false));

        self::assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testRolesAreDeduplicated(): void
    {
        $user = (new User())
            ->addAssignedRole((new Role())->setCode('direction')->setAdmin(true))
            ->addAssignedRole((new Role())->setCode('ems_manager')->setAdmin(true));

        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
    }
}
