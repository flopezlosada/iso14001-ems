<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ObligationAlertNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Machine-to-machine entry point that fires the obligation reminders, so the engine can be triggered
 * on shared hosting whose only scheduler is an HTTP cron (cdmon/IONOS fire a plain HTTP GET; they
 * cannot run a shell command nor set request headers). The {@see \App\Command\SendObligationAlertsCommand}
 * stays the canonical way to run it from an SSH/CLI cron; this controller is the same job reachable
 * over HTTP — both delegate to the very same {@see ObligationAlertNotifier}, so there is no duplicated
 * logic.
 *
 * Trade-offs accepted on purpose:
 *  - It is a GET with a side effect (not REST-pure) because the HTTP cron can only issue GETs. The
 *    job is idempotent ({@see \App\Entity\ScheduledAlert::needsNotification()} + lastNotifiedAt), so a
 *    repeated or stray GET sends no duplicate e-mails.
 *  - The token travels in the query string (the cron cannot send an Authorization header), so it can
 *    surface in access logs. It is therefore a low-value, rotatable secret over HTTPS, never a user
 *    credential.
 *
 * Security: it fails closed. With no CRON_SECRET configured the endpoint is disabled (403); the token
 * is compared in constant time to thwart timing attacks. The firewall lets it through unauthenticated
 * (^/cron is PUBLIC_ACCESS) precisely because the secret token is the authentication.
 */
class ObligationAlertCronController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(CRON_SECRET)%')]
        private readonly string $cronSecret,
    ) {
    }

    /**
     * Triggers the due obligation reminders when called with the correct shared secret.
     *
     * @param Request                  $request  the incoming request; the secret is read from ?token=
     * @param ObligationAlertNotifier  $notifier the engine that resolves recipients and sends the e-mails
     *
     * @return Response a JSON summary (200) on success, or 403 when the secret is missing/unset/wrong
     */
    #[Route('/cron/obligation-alerts', name: 'cron_obligation_alerts', methods: ['GET'])]
    public function run(Request $request, ObligationAlertNotifier $notifier): Response
    {
        // Fail closed: an unset secret means the endpoint is not enabled, so reject every call
        // (hash_equals('', '') would otherwise accept an empty token).
        $token = (string) $request->query->get('token', '');
        if ('' === $this->cronSecret || !hash_equals($this->cronSecret, $token)) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $summary = $notifier->notify(new \DateTimeImmutable('today'));

        return new JsonResponse($summary);
    }
}
