<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Role;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use Doctrine\Persistence\ObjectManager;

/**
 * The environmental-management responsibilities of the centre (configurable catalog, not an enum).
 *
 * Part of the GOLDEN backbone: these roles, with their per-area permissions, are the access
 * skeleton the eventual production database would start from. Synthetic but modelled on the real
 * organigrama (Dirección, Responsable del SGA, Calidad, Mantenimiento, Secretaría).
 */
final class RoleFixtures extends AbstractGoldenFixture
{
    /** Reference name for the role with the given code, so other fixtures can wire to it. */
    public static function ref(string $code): string
    {
        return 'role-'.$code;
    }

    public function load(ObjectManager $manager): void
    {
        // code => [display name, area => level]. Areas absent from the map grant no access.
        $w = PermissionLevel::WRITE;
        $r = PermissionLevel::READ;
        $all = array_fill_keys(array_map(static fn (Area $a) => $a->value, Area::cases()), $w);

        $roles = [
            'admin' => ['Administrador', $all],
            'direction' => ['Dirección', $all],
            'ems_manager' => ['Responsable del SGA', $all],
            'quality' => ['Responsable de Calidad', [
                Area::NONCONFORMITY->value => $w,
                Area::ASPECT->value => $r,
                Area::LEGAL_REQUIREMENT->value => $r,
                Area::SUPPLIER->value => $r,
            ]],
            'maintenance' => ['Mantenimiento', [
                Area::CONSUMPTION->value => $w,
                Area::WASTE->value => $w,
                Area::EMERGENCY->value => $w,
            ]],
            'secretary' => ['Secretaría', [
                Area::TRAINING->value => $w,
                Area::SUPPLIER->value => $w,
                Area::LEGAL_REQUIREMENT->value => $r,
            ]],
            // Responsibles that appear in the document register but have no module yet: they own
            // "pending-module" obligations (upload a file / mark done). No area grants until their
            // module exists — consistent with Area only listing modules that are actually built.
            'cfpg' => ['Resp. Mantenimiento (CFGS Jardinería)', []],
            'cleaning' => ['Personal de Limpieza y Mantenimiento', []],
        ];

        foreach ($roles as $code => [$name, $permissions]) {
            $role = new Role();
            $role->setCode($code)->setName($name);
            foreach ($permissions as $area => $level) {
                $role->setLevel(Area::from($area), $level);
            }
            $manager->persist($role);
            $this->addReference(self::ref($code), $role);
        }

        $manager->flush();
    }
}
