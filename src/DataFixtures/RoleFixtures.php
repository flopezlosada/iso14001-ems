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

    /**
     * The role catalog as domain objects, keyed by code. Kept separate from persistence so the
     * backbone invariants — in particular which role carries the admin flag — can be asserted in
     * isolation without spinning up the database.
     *
     * @return array<string, Role>
     */
    public static function catalog(): array
    {
        // code => [display name, description, area => level]. Areas absent from the map grant no
        // access. The description is shown to the admin when assigning roles to a person.
        $w = PermissionLevel::WRITE;
        $r = PermissionLevel::READ;
        $all = array_fill_keys(array_map(static fn (Area $a) => $a->value, Area::cases()), $w);
        $allRead = array_fill_keys(array_map(static fn (Area $a) => $a->value, Area::cases()), $r);

        $roles = [
            'admin' => ['Administrador', 'Acceso total y administración de usuarios, roles y configuración del sistema.', $all],
            'direction' => ['Dirección', 'Dirección del centro: acceso completo a todas las áreas del SGA.', $all],
            'ems_manager' => ['Responsable del SGA', 'Responsable del Sistema de Gestión Ambiental: gestiona todas las áreas.', $all],
            'quality' => ['Responsable de Calidad', 'Gestiona las no conformidades; consulta aspectos, requisitos legales y proveedores.', [
                Area::NONCONFORMITY->value => $w,
                Area::ASPECT->value => $r,
                Area::LEGAL_REQUIREMENT->value => $r,
                Area::SUPPLIER->value => $r,
            ]],
            'maintenance' => ['Mantenimiento', 'Registra consumos, residuos y simulacros de emergencia.', [
                Area::CONSUMPTION->value => $w,
                Area::WASTE->value => $w,
                Area::EMERGENCY->value => $w,
            ]],
            'secretary' => ['Secretaría', 'Gestiona formación y proveedores; consulta los requisitos legales.', [
                Area::TRAINING->value => $w,
                Area::SUPPLIER->value => $w,
                Area::LEGAL_REQUIREMENT->value => $r,
            ]],
            // Functional responsibility role (no area module is "owned" by IT): exists so it can be the
            // responsible of risk action-plan items (e.g. "RESPO INFORMÁTICA" in the F.08.0). No grants.
            'it' => ['Responsable de Informática', 'Responsable de los sistemas informáticos del centro; puede figurar como responsable de acciones del plan de riesgos.', []],
            // Personnel in charge of machinery and infrastructure (informe ISO 14001): operational
            // control is their record and they take part in emergency drills. Earlier these roles
            // were left empty "until their module exists" — but the operational-control module is
            // built, so the grant is now real (decidido con dirección 2026-06-28).
            'cfpg' => ['Resp. Mantenimiento (CFGS Jardinería)', 'Control de maquinaria e infraestructuras: control operacional y simulacros.', [
                Area::OPERATIONAL_CONTROL->value => $w,
                Area::EMERGENCY->value => $w,
            ]],
            'cleaning' => ['Personal de Limpieza y Mantenimiento', 'Limpieza y mantenimiento: control operacional y simulacros.', [
                Area::OPERATIONAL_CONTROL->value => $w,
                Area::EMERGENCY->value => $w,
            ]],
            // External auditor: read-only access to every content area for the certification audit,
            // never write, never admin (informe ISO 14001, sección 6).
            'auditor' => ['Auditor externo', 'Acceso de solo lectura a todas las áreas para la auditoría externa.', $allRead],
        ];

        $catalog = [];
        foreach ($roles as $code => [$name, $description, $permissions]) {
            $role = new Role();
            // Only the 'admin' role carries the admin flag. The runtime migration backfills
            // existing databases; a fresh fixtures load (local/CI/clean deploy) needs it set here
            // or no user would ever obtain ROLE_ADMIN and /admin + /audit would be locked out.
            $role->setCode($code)->setName($name)->setDescription($description)->setAdmin('admin' === $code);
            foreach ($permissions as $area => $level) {
                $role->setLevel(Area::from($area), $level);
            }
            $catalog[$code] = $role;
        }

        return $catalog;
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::catalog() as $code => $role) {
            $manager->persist($role);
            $this->addReference(self::ref($code), $role);
        }

        $manager->flush();
    }
}
