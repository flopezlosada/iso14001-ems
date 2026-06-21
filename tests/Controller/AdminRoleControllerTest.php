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
