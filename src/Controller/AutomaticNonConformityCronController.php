<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AutomaticNonConformityGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Machine-to-machine entry point that runs the automatic non-conformity engine, so it can be
 * triggered on shared hosting whose only scheduler is an HTTP cron (a plain GET; no shell, no
 * request headers). The {@see \App\Command\GenerateAutomaticNonConformitiesCommand} stays the
 * canonical CLI way; both delegate to the same {@see AutomaticNonConformityGenerator}, so there is
 * no duplicated logic.
 *
 * Mirrors {@see ObligationAlertCronController}: a GET with a side effect (the HTTP cron can only
 * issue GETs), idempotent (the engine keys each non-conformity to its source), token in the query
 * string (no header possible) — a low-value, rotatable secret over HTTPS. It fails closed: with no
 * CRON_SECRET configured the endpoint is disabled (403), and the token is compared in constant time.
 * The firewall lets ^/cron through unauthenticated precisely because the token is the authentication.
 */
class AutomaticNonConformityCronController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(CRON_SECRET)%')]
        private readonly string $cronSecret,
    ) {
    }

    /**
     * Runs the auto-non-conformity engine when called with the correct shared secret.
     *
     * @param Request                            $request   the incoming request; the secret is read from ?token=
     * @param AutomaticNonConformityGenerator    $generator the engine that opens the non-conformities
     *
     * @return Response a JSON summary (200) on success, or 403 when the secret is missing/unset/wrong
     */
    #[Route('/cron/auto-nonconformities', name: 'cron_auto_nonconformities', methods: ['GET'])]
    public function run(Request $request, AutomaticNonConformityGenerator $generator): Response
    {
        // Fail closed: an unset secret means the endpoint is not enabled, so reject every call
        // (hash_equals('', '') would otherwise accept an empty token).
        $token = (string) $request->query->get('token', '');
        if ('' === $this->cronSecret || !hash_equals($this->cronSecret, $token)) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $summary = $generator->generate(new \DateTimeImmutable('today'));

        return new JsonResponse($summary);
    }
}
