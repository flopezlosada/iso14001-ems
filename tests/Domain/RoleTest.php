<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\Role;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use PHPUnit\Framework\TestCase;

/**
 * Domain invariants of the role permission map: unset areas grant no access, WRITE includes
 * READ, and setting NONE clears the entry.
 */
final class RoleTest extends TestCase
{
    public function testUnsetAreaDefaultsToNone(): void
    {
        self::assertSame(PermissionLevel::NONE, (new Role())->getLevel(Area::CONSUMPTION));
    }

    public function testWriteLevelAllowsReadAndWrite(): void
    {
        $role = (new Role())->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE);

        self::assertSame(PermissionLevel::WRITE, $role->getLevel(Area::CONSUMPTION));
        self::assertTrue($role->allows(Area::CONSUMPTION, PermissionLevel::READ));
        self::assertTrue($role->allows(Area::CONSUMPTION, PermissionLevel::WRITE));
    }

    public function testSettingNoneClearsTheLevel(): void
    {
        $role = (new Role())
            ->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE)
            ->setLevel(Area::CONSUMPTION, PermissionLevel::NONE);

        self::assertSame(PermissionLevel::NONE, $role->getLevel(Area::CONSUMPTION));
        self::assertFalse($role->allows(Area::CONSUMPTION, PermissionLevel::READ));
    }
}
