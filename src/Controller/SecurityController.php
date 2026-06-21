<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Passwordless authentication: the user requests a magic link by e-mail and follows it to sign
 * in. Only registered (allow-listed) users receive a link.
 */
class SecurityController extends AbstractController
{
    /**
     * @param string $googleClientId the Google/Educamadrid OAuth client id; empty until
     *                               credentials are set in .env.local, which keeps the SSO
     *                               button disabled
     */
    public function __construct(
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private readonly string $googleClientId,
        #[Autowire(service: 'limiter.magic_link')]
        private readonly RateLimiterFactory $magicLinkLimiter,
    ) {
    }

    /**
     * Shows the e-mail form and, on submit, sends a magic login link to a known user.
     */
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request, UserRepository $users, LoginLinkHandlerInterface $loginLinkHandler, MailerInterface $mailer): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('security/login.html.twig', [
                'google_sso_enabled' => '' !== $this->googleClientId,
            ]);
        }

        // Throttle per IP to prevent abuse / mail-bombing a user with link requests.
        if (!$this->magicLinkLimiter->create($request->getClientIp() ?? 'anonymous')->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Has solicitado demasiados enlaces de acceso. Inténtalo más tarde.');
        }

        $email = trim((string) $request->request->get('email', ''));
        $user = '' !== $email ? $users->findActiveByEmail($email) : null;

        if (null !== $user) {
            $loginLink = $loginLinkHandler->createLoginLink($user);
            $mailer->send((new Email())
                ->from('no-reply@iso14001-ems.local')
                ->to($user->getEmail())
                ->subject('Tu enlace de acceso')
                ->text("Entra en la aplicación con este enlace (válido 10 minutos):\n\n".$loginLink->getUrl()));
        }

        // Always show the same confirmation, even if the e-mail is unknown, so the page does
        // not reveal which addresses are registered.
        return $this->render('security/link_sent.html.twig', ['email' => $email]);
    }

    /**
     * Target of the magic link; the request is intercepted and processed by the login_link
     * authenticator, so this method is never executed.
     */
    #[Route('/login/check', name: 'login_check', methods: ['GET'])]
    public function check(): never
    {
        throw new \LogicException('This route is handled by the login_link authenticator.');
    }

    /**
     * Logout; intercepted by the logout firewall, so this method is never executed.
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This route is handled by the logout firewall.');
    }
}
