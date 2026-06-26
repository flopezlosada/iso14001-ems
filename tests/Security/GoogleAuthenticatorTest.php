<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\GoogleAuthenticator;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Unit tests for {@see GoogleAuthenticator}. The access control is the allow-list (registered,
 * active users); the e-mail domain is intentionally NOT checked. These tests pin that contract.
 */
final class GoogleAuthenticatorTest extends TestCase
{
    /**
     * Builds the authenticator with a Google client stubbed to return the given resource owner.
     * The OAuth client, registry and URL generator are stubs (we only need return values); the
     * UserRepository is supplied by the caller so a test can mock it when it verifies the lookup.
     *
     * @param ResourceOwnerInterface|null $resourceOwner what fetchUserFromToken() yields
     */
    private function authenticator(UserRepository $users, ?ResourceOwnerInterface $resourceOwner): GoogleAuthenticator
    {
        $client = $this->createStub(OAuth2ClientInterface::class);
        $client->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 'fake-token']));
        $client->method('fetchUserFromToken')->willReturn($resourceOwner);

        $registry = $this->createStub(ClientRegistry::class);
        $registry->method('getClient')->willReturn($client);

        return new GoogleAuthenticator($registry, $users, $this->createStub(UrlGeneratorInterface::class));
    }

    public function testSupportsOnlyTheCallbackRoute(): void
    {
        $authenticator = $this->authenticator($this->createStub(UserRepository::class), null);

        $callback = new Request();
        $callback->attributes->set('_route', 'connect_google_check');
        self::assertTrue($authenticator->supports($callback));

        $other = new Request();
        $other->attributes->set('_route', 'login');
        self::assertFalse($authenticator->supports($other));
    }

    public function testAuthenticateResolvesActiveAllowListedUser(): void
    {
        $user = new User();
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())
            ->method('findActiveByEmail')
            ->with('paco.lopez@toq.io')
            ->willReturn($user);

        $passport = $this->authenticator($users, new GoogleUser(['email' => 'paco.lopez@toq.io', 'sub' => '1']))
            ->authenticate(new Request());

        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame('paco.lopez@toq.io', $badge->getUserIdentifier());
        self::assertSame($user, ($badge->getUserLoader())('paco.lopez@toq.io'));
    }

    public function testAuthenticateNormalizesEmailBeforeLookup(): void
    {
        $user = new User();
        // The raw Google e-mail is mixed-case and padded; the lookup must receive it normalized.
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())
            ->method('findActiveByEmail')
            ->with('paco.lopez@toq.io')
            ->willReturn($user);

        $passport = $this->authenticator($users, new GoogleUser(['email' => '  Paco.Lopez@TOQ.io ', 'sub' => '1']))
            ->authenticate(new Request());

        $badge = $passport->getBadge(UserBadge::class);
        self::assertSame('paco.lopez@toq.io', $badge->getUserIdentifier());
        self::assertSame($user, ($badge->getUserLoader())('paco.lopez@toq.io'));
    }

    public function testAuthenticateRejectsUserNotInAllowList(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findActiveByEmail')->willReturn(null);

        $passport = $this->authenticator($users, new GoogleUser(['email' => 'stranger@toq.io', 'sub' => '1']))
            ->authenticate(new Request());

        // The allow-list check runs when the badge resolves the user.
        $this->expectException(CustomUserMessageAuthenticationException::class);
        ($passport->getBadge(UserBadge::class)->getUserLoader())('stranger@toq.io');
    }

    public function testAuthenticateRejectsUnexpectedProviderResponse(): void
    {
        $authenticator = $this->authenticator(
            $this->createStub(UserRepository::class),
            $this->createStub(ResourceOwnerInterface::class),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate(new Request());
    }
}
