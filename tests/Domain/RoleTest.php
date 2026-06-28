<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\DataFixtures\RoleFixtures;
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

    public function testIsAdminDefaultsToFalseAndToggles(): void
    {
        self::assertFalse((new Role())->isAdmin());
        self::assertTrue((new Role())->setAdmin(true)->isAdmin());
    }

    public function testSeededCatalogGrantsAdminOnlyToTheAdminRole(): void
    {
        $catalog = RoleFixtures::catalog();

        self::assertArrayHasKey('admin', $catalog);
        self::assertTrue($catalog['admin']->isAdmin(), 'The seeded admin role must carry ROLE_ADMIN on a fresh database');

        foreach ($catalog as $code => $role) {
            if ('admin' !== $code) {
                self::assertFalse($role->isAdmin(), sprintf('Role "%s" must not be admin', $code));
            }
        }
    }

    public function testLevelCountsTallyAreasPerLevel(): void
    {
        $role = (new Role())
            ->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE)
            ->setLevel(Area::WASTE, PermissionLevel::WRITE)
            ->setLevel(Area::SUPPLIER, PermissionLevel::READ);

        $counts = $role->levelCounts();

        self::assertSame(2, $counts['write']);
        self::assertSame(1, $counts['read']);
        self::assertSame(\count(Area::cases()) - 3, $counts['none']);
    }

    public function testSeededAuditorIsReadOnlyOnEveryAreaAndNotAdmin(): void
    {
        $auditor = RoleFixtures::catalog()['auditor'] ?? null;
        self::assertNotNull($auditor, 'The external-auditor role must be seeded');
        self::assertFalse($auditor->isAdmin());

        foreach (Area::cases() as $area) {
            self::assertSame(PermissionLevel::READ, $auditor->getLevel($area), sprintf('Auditor must read %s', $area->value));
        }
        self::assertSame(\count(Area::cases()), $auditor->levelCounts()['read']);
        self::assertSame(0, $auditor->levelCounts()['write']);
    }

    public function testSeededMaintenanceRolesGrantOperationalControlAndDrills(): void
    {
        $catalog = RoleFixtures::catalog();

        foreach (['cfpg', 'cleaning'] as $code) {
            $role = $catalog[$code] ?? null;
            self::assertNotNull($role, sprintf('Role "%s" must be seeded', $code));
            self::assertSame(PermissionLevel::WRITE, $role->getLevel(Area::OPERATIONAL_CONTROL), sprintf('%s writes operational control', $code));
            self::assertSame(PermissionLevel::WRITE, $role->getLevel(Area::EMERGENCY), sprintf('%s writes emergency drills', $code));
        }
    }

    public function testEverySeededRoleHasADescription(): void
    {
        foreach (RoleFixtures::catalog() as $code => $role) {
            self::assertNotEmpty($role->getDescription(), sprintf('Role "%s" should describe what it can do', $code));
        }
    }
}
