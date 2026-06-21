<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Functional tests for passwordless (magic-link) authentication and access control.
 * Database writes and sent e-mails are isolated per test.
 */
final class SecurityControllerTest extends WebTestCase
{
    private function persistUser(string $email): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('Tester')->setEmail($email)->setActive(true);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testLoginPageIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="email"]');
        // SSO button is present but disabled until credentials are configured.
        self::assertSelectorExists('button[disabled]');
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/consumption/2026');

        self::assertResponseRedirects('/login');
    }

    public function testMagicLinkAuthenticatesAndGrantsAccess(): void
    {
        $client = static::createClient();
        $user = $this->persistUser('linker@example.test');

        // Use the firewall-specific handler: the generic one needs an active request to
        // resolve the firewall.
        $handler = static::getContainer()->get('security.authenticator.login_link_handler.main');
        \assert($handler instanceof LoginLinkHandlerInterface);
        $loginLink = $handler->createLoginLink($user);
        $client->request('GET', $loginLink->getUrl());
        self::assertResponseRedirects();

        $client->request('GET', '/consumption/2026');
        self::assertResponseIsSuccessful();
    }

    public function testRequestingLinkEmailsAKnownUser(): void
    {
        $client = static::createClient();
        $this->persistUser('known@example.test');

        $client->request('POST', '/login', ['email' => 'known@example.test']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Revisa tu correo');
        self::assertEmailCount(1);
    }

    public function testRequestingLinkForUnknownEmailSendsNothing(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', ['email' => 'nobody@example.test']);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(0);
    }

    public function testRequestingLinkForInactiveUserSendsNothing(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('Inactivo')->setEmail('inactive@example.test')->setActive(false);
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/login', ['email' => 'inactive@example.test']);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(0);
    }

    public function testAnonymousRootEndsOnLoginPage(): void
    {
        $client = static::createClient();
        $client->followRedirects();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="email"]');
    }
}
