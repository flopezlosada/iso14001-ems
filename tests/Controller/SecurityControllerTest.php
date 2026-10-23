<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Functional tests for passwordless (magic-link) authentication and access control.
 * Database writes and sent e-mails are isolated per test.
 */
final class SecurityControllerTest extends WebTestCase
{
    private function persistUser(string $email, bool $admin = false): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('Tester')->setEmail($email)->setActive(true);

        if ($admin) {
            $role = new Role();
            $role->setCode('admin')->setName('Administrador')->setAdmin(true);
            $em->persist($role);
            $user->addAssignedRole($role);
        }

        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Follows a magic link for the given user and returns the remember-me cookie it sets.
     */
    private function signInAndGetRememberMeCookie(KernelBrowser $client, User $user): Cookie
    {
        $handler = static::getContainer()->get('security.authenticator.login_link_handler.main');
        \assert($handler instanceof LoginLinkHandlerInterface);
        $client->request('GET', $handler->createLoginLink($user)->getUrl());
        self::assertResponseRedirects();

        $cookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($cookie, 'The magic link must set the remember-me cookie.');

        return $cookie;
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
        $user = $this->persistUser('linker@example.test', admin: true);

        // Use the firewall-specific handler: the generic one needs an active request to
        // resolve the firewall.
        $handler = static::getContainer()->get('security.authenticator.login_link_handler.main');
        \assert($handler instanceof LoginLinkHandlerInterface);
        $loginLink = $handler->createLoginLink($user);
        $client->request('GET', $loginLink->getUrl());
        self::assertResponseRedirects();

        $client->request('GET', '/consumption/2026');
        self::assertResponseIsSuccessful();

        // The successful sign-in is recorded in the activity trail.
        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'user.login'])
        );
    }

    public function testMagicLinkKeepsUserSignedInAfterSessionIsLost(): void
    {
        $client = static::createClient();
        $rememberMe = $this->signInAndGetRememberMeCookie($client, $this->persistUser('remembered@example.test', admin: true));

        // A fresh cookie jar simulates closing the browser / the session expiring.
        $client->restart();
        $client->getCookieJar()->set($rememberMe);
        $client->request('GET', '/consumption/2026');

        self::assertResponseIsSuccessful();
    }

    public function testRememberMeCookieIsRejectedOnceUserIsDeactivated(): void
    {
        $client = static::createClient();
        $user = $this->persistUser('revoked@example.test', admin: true);
        $rememberMe = $this->signInAndGetRememberMeCookie($client, $user);

        $user->setActive(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->restart();
        $client->getCookieJar()->set($rememberMe);
        $client->request('GET', '/consumption/2026');

        self::assertResponseRedirects('/login');
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

    public function testBlankEmailReshowsLoginWithErrorAndSendsNothing(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', ['email' => '   ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'correo electrónico válido');
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
