<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for admin role management (the permission matrix). Admins only.
 */
final class AdminRoleControllerTest extends WebTestCase
{
    private function persistAdmin(): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = new Role();
        $role->setCode('admin')->setName('Administrador')->setAdmin(true);
        $em->persist($role);
        $user = new User();
        $user->setFullName('Admin')->setEmail('roleadmin@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/roles');

        self::assertResponseRedirects('/login');
    }

    public function testForbiddenForNonAdmin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('User')->setEmail('plain@example.test')->setActive(true);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/admin/roles');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexListsRolesWithAOneLinePermissionSummary(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = new Role();
        $role->setCode('mant')->setName('Mantenimiento')
            ->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE)
            ->setLevel(Area::WASTE, PermissionLevel::WRITE)
            ->setLevel(Area::SUPPLIER, PermissionLevel::READ);
        $em->persist($role);
        $em->flush();

        $client->loginUser($this->persistAdmin());
        $client->request('GET', '/admin/roles');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.role-list', 'Mantenimiento');
        self::assertSelectorTextContains('.role-list', 'Escritura en 2 · Lectura en 1 · Sin acceso al resto');
    }

    public function testIndexSummarisesARoleWithNoPermissions(): void
    {
        // A freshly created role (or one being configured) grants nothing: the summary must read
        // "Sin acceso a ninguna área", not an empty line.
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = new Role();
        $role->setCode('vacio')->setName('Rol sin permisos');
        $em->persist($role);
        $em->flush();

        $client->loginUser($this->persistAdmin());
        $client->request('GET', '/admin/roles');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.role-list', 'Sin acceso a ninguna área');
    }

    public function testAdminCanCreateRoleWithPermissions(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistAdmin());

        $crawler = $client->request('GET', '/admin/roles/new');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="role[_token]"]')->attr('value');

        $client->request('POST', '/admin/roles/new', ['role' => [
            'code' => 'consumos',
            'name' => 'Gestión de consumos',
            'description' => '',
            'perm_consumption' => PermissionLevel::WRITE->value,
            '_token' => $token,
        ]]);
        self::assertResponseRedirects('/admin/roles');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $role = $em->getRepository(Role::class)->findOneBy(['code' => 'consumos']);
        self::assertNotNull($role);
        self::assertSame(PermissionLevel::WRITE, $role->getLevel(Area::CONSUMPTION));
    }
}
