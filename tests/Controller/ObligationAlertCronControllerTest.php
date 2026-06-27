<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ObligationAlertCronController;
use App\Entity\Document;
use App\Entity\Role;
use App\Entity\ScheduledAlert;
use App\Entity\User;
use App\Enum\AlertFrequency;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Service\ObligationAlertNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Functional tests for the HTTP cron entry point that fires the obligation reminders. They cover the
 * token gate (no token / wrong token are rejected without running the engine) and the end-to-end
 * success path: a correct token reaches the same notifier as the CLI command, so a due reminder is
 * actually e-mailed and stamped. The known secret comes from .env.test (CRON_SECRET).
 */
final class ObligationAlertCronControllerTest extends WebTestCase
{
    private const string TOKEN = 'test-cron-secret';
    private const string URL = '/cron/obligation-alerts';

    public function testRejectsRequestWithoutToken(): void
    {
        $client = static::createClient();
        $client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(403);
        // The error shape is part of the machine-to-machine contract.
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['error' => 'Forbidden'], json_decode((string) $client->getResponse()->getContent(), true));
        self::assertEmailCount(0);
    }

    public function testRejectsWrongToken(): void
    {
        $client = static::createClient();
        $client->request('GET', self::URL, ['token' => 'not-the-secret']);

        self::assertResponseStatusCodeSame(403);
        self::assertEmailCount(0);
    }

    public function testValidTokenFiresTheEngineEndToEnd(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // A due obligation owned by a role held by an active user → exactly one reminder must go out.
        $role = (new Role())->setCode('secretary')->setName('Secretaría');
        $em->persist($role);
        $user = (new User())->setFullName('Ana Secretaría')->setEmail('ana-cron@example.test')->setActive(true);
        $user->addAssignedRole($role);
        $em->persist($user);

        $alert = (new ScheduledAlert())->setFrequency(AlertFrequency::MONTHLY)->setNextDueDate(new \DateTimeImmutable('2000-01-01'));
        $alert->addRecipientRole($role);
        $document = (new Document())
            ->setCode('CRON-ALERT')
            ->setTitle('Obligación vencida')
            ->setType(DocumentType::FORM)
            ->setIsoChapter(IsoChapter::PLANNING)
            ->addAlert($alert);
        $em->persist($document);
        $em->flush();
        $alertId = $alert->getId();

        $client->request('GET', self::URL, ['token' => self::TOKEN]);

        self::assertResponseIsSuccessful();
        /** @var array{alerts: int, emails: int, withoutRecipient: int} $summary */
        $summary = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(1, $summary['alerts']);
        self::assertSame(1, $summary['emails']);
        self::assertSame(0, $summary['withoutRecipient']);
        self::assertEmailCount(1);

        // The engine ran for real: the alert is stamped so it is not re-sent within the same cycle.
        $em->clear();
        $stamped = $em->getRepository(ScheduledAlert::class)->find($alertId);
        self::assertNotNull($stamped);
        self::assertNotNull($stamped->getLastNotifiedAt(), 'a notified alert must be stamped');
    }

    /**
     * Fail-closed guard: with no secret configured the endpoint must reject every call, including an
     * empty token — otherwise hash_equals('', '') would accept it and run the engine unprotected.
     */
    public function testEmptySecretFailsClosedEvenWithEmptyToken(): void
    {
        // Pure unit check of the fail-closed guard: with no secret configured, the endpoint must
        // reject even an empty token (hash_equals('', '') would otherwise accept it) and must not
        // reach the notifier. The notifier is built without its constructor precisely to prove it is
        // never used — any call on it here would fatal on its uninitialised state.
        $notifier = (new \ReflectionClass(ObligationAlertNotifier::class))->newInstanceWithoutConstructor();
        $controller = new ObligationAlertCronController('');

        $response = $controller->run(Request::create(self::URL, 'GET', ['token' => '']), $notifier);

        self::assertSame(403, $response->getStatusCode());
    }
}
