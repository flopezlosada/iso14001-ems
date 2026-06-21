<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the per-area read/write authorization (AreaVoter), exercised through the
 * consumption module.
 */
final class AuthorizationTest extends WebTestCase
{
    private int $sequence = 0;

    private function clientFor(?PermissionLevel $consumptionLevel, bool $admin = false): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setFullName('Tester')->setEmail('authz'.(++$this->sequence).'@example.test')->setActive(true);

        if ($admin) {
            $role = new Role();
            $role->setCode('admin')->setName('Administrador')->setAdmin(true);
            $em->persist($role);
            $user->addAssignedRole($role);
        } elseif (null !== $consumptionLevel) {
            $role = new Role();
            $role->setCode('consumos')->setName('Consumos')->setLevel(Area::CONSUMPTION, $consumptionLevel);
            $em->persist($role);
            $user->addAssignedRole($role);
        }

        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testWithoutPermissionTheListIsForbidden(): void
    {
        $client = $this->clientFor(null);
        $client->request('GET', '/consumption/2026');

        self::assertResponseStatusCodeSame(403);
    }

    public function testReadPermissionCanListButNotCreate(): void
    {
        $client = $this->clientFor(PermissionLevel::READ);

        $client->request('GET', '/consumption/2026');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/consumption/2026/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testWritePermissionCanCreate(): void
    {
        $client = $this->clientFor(PermissionLevel::WRITE);
        $client->request('GET', '/consumption/2026/new');

        self::assertResponseIsSuccessful();
    }

    public function testAdminBypassesTheMatrix(): void
    {
        $client = $this->clientFor(null, admin: true);
        $client->request('GET', '/consumption/2026/new');

        self::assertResponseIsSuccessful();
    }

    public function testIndexRedirectAlsoRequiresRead(): void
    {
        $client = $this->clientFor(null);
        $client->request('GET', '/consumption');

        self::assertResponseStatusCodeSame(403);
    }

    public function testMostPermissiveRoleWins(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $readOnly = (new Role())->setCode('lectura')->setName('Lectura')->setLevel(Area::CONSUMPTION, PermissionLevel::READ);
        $writer = (new Role())->setCode('escritura')->setName('Escritura')->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE);
        $em->persist($readOnly);
        $em->persist($writer);
        $user = (new User())->setFullName('Multi')->setEmail('multi@example.test')->setActive(true)
            ->addAssignedRole($readOnly)->addAssignedRole($writer);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/consumption/2026/new');
        self::assertResponseIsSuccessful();
    }

    public function testInactiveUserIsForbidden(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('consumos')->setName('Consumos')->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Inactivo')->setEmail('inactivo@example.test')->setActive(false)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/consumption/2026');
        self::assertResponseStatusCodeSame(403);
    }
}
