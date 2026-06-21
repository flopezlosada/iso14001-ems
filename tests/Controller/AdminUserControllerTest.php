<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for admin user management (the access allow-list). Admins only.
 */
final class AdminUserControllerTest extends WebTestCase
{
    private function persistUser(string $email, bool $admin): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('Tester')->setEmail($email)->setActive(true);

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

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users');

        self::assertResponseRedirects('/login');
    }

    public function testForbiddenForNonAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser('user@example.test', false));
        $client->request('GET', '/admin/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanRegisterAUserWithARole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('consumos')->setName('Gestión de consumos');
        $em->persist($role);
        $em->flush();

        $client->loginUser($this->persistUser('admin@example.test', true));

        $crawler = $client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();

        // Post the form directly (with its CSRF token) so role selection does not depend on
        // checkbox ordering in the rendered HTML.
        $token = (string) $crawler->filter('input[name="user[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/new', ['user' => [
            'fullName' => 'Nuevo Usuario',
            'email' => 'nuevo@educa.madrid.test',
            'active' => '1',
            'assignedRoles' => [(string) $role->getId()],
            '_token' => $token,
        ]]);

        self::assertResponseRedirects('/admin/users');

        $created = static::getContainer()->get(UserRepository::class)->findActiveByEmail('nuevo@educa.madrid.test');
        self::assertNotNull($created);
        self::assertCount(1, $created->getAssignedRoles());
    }

    public function testEditingUserCanRemoveARole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('consumos')->setName('Consumos');
        $em->persist($role);
        $target = new User();
        $target->setFullName('Con Rol')->setEmail('conrol@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($target);
        $em->flush();
        $targetId = $target->getId();

        $client->loginUser($this->persistUser('admin2@example.test', true));

        $crawler = $client->request('GET', '/admin/users/'.$targetId.'/edit');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="user[_token]"]')->attr('value');

        // Submit with no roles selected -> the previously assigned role must be removed.
        $client->request('POST', '/admin/users/'.$targetId.'/edit', ['user' => [
            'fullName' => 'Con Rol',
            'email' => 'conrol@example.test',
            'active' => '1',
            '_token' => $token,
        ]]);
        self::assertResponseRedirects('/admin/users');

        $em2 = static::getContainer()->get(EntityManagerInterface::class);
        $em2->clear();
        $reloaded = $em2->getRepository(User::class)->findOneBy(['email' => 'conrol@example.test']);
        self::assertNotNull($reloaded);
        self::assertCount(0, $reloaded->getAssignedRoles());
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $existing = new User();
        $existing->setFullName('Existe')->setEmail('dup@example.test')->setActive(true);
        $em->persist($existing);
        $em->flush();

        $client->loginUser($this->persistUser('admin3@example.test', true));

        $crawler = $client->request('GET', '/admin/users/new');
        $token = (string) $crawler->filter('input[name="user[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/new', ['user' => [
            'fullName' => 'Otro',
            'email' => 'dup@example.test',
            'active' => '1',
            '_token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul li', 'Ya existe');
    }
}
