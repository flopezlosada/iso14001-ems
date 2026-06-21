<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the read-only activity trail view (admins only).
 */
final class AuditControllerTest extends WebTestCase
{
    private function persistUser(bool $admin): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('Tester')
            ->setEmail(($admin ? 'admin' : 'user').'@example.test')
            ->setActive(true);

        if ($admin) {
            $role = new Role();
            $role->setCode('admin')->setName('Administrador');
            $em->persist($role);
            $user->addAssignedRole($role);
        }

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testAuditPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/audit');

        self::assertResponseRedirects('/login');
    }

    public function testAuditPageRendersForAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser(true));
        $client->request('GET', '/audit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Registro de actividad');
    }

    public function testAuditPageForbiddenForNonAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser(false));
        $client->request('GET', '/audit');

        self::assertResponseStatusCodeSame(403);
    }
}
