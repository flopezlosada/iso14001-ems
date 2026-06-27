<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Communication;
use App\Entity\InterestedParty;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\CommunicationCategory;
use App\Enum\CommunicationChannel;
use App\Enum\CommunicationScope;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\CommunicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the communications register UI (RG-07.04.00, §7.4). Routes
 * require an authenticated user with COMMUNICATION permission; each test logs one in. Database
 * writes are rolled back after each test by DAMA DoctrineTestBundle.
 */
final class CommunicationControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('comunicaciones')->setName('Gestión de comunicaciones')->setLevel(Area::COMMUNICATION, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('comms-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/communications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Comunicaciones');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/communications/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input#communication_subject');
    }

    public function testSubmittingValidCommunicationPersistsItAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/communications/new');

        // Realistic data shape: an internal communication with no related party and blank optionals.
        $client->submitForm('Guardar', [
            'communication[occurredOn]' => '2026-03-12',
            'communication[scope]' => CommunicationScope::INTERNAL->value,
            'communication[category]' => CommunicationCategory::INFORMATION->value,
            'communication[channel]' => CommunicationChannel::MEETING->value,
            'communication[subject]' => 'Comunicación de la política ambiental',
            'communication[details]' => '',
            'communication[sender]' => 'Responsable SGA',
            'communication[recipient]' => '',
            'communication[response]' => '',
        ]);

        self::assertResponseRedirects('/communications');

        $all = static::getContainer()->get(CommunicationRepository::class)->findAllOrdered();
        self::assertCount(1, $all);
        $communication = $all[0];
        self::assertSame('Comunicación de la política ambiental', $communication->getSubject());
        self::assertSame(CommunicationScope::INTERNAL, $communication->getScope());
        // Blank optionals are stored as null, not empty strings.
        self::assertNull($communication->getDetails());
        self::assertNull($communication->getRecipient());
        self::assertNull($communication->getInterestedParty());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'communication.created'])
        );

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Comunicación de la política ambiental');
    }

    public function testSubmittingComplaintLinkedToAnInterestedPartyStoresTheLink(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $party = (new InterestedParty())->setReviewYear(2026)->setName('Gestores de residuos')->setNeedsAndExpectations('Segregación correcta.');
        $em->persist($party);
        $em->flush();

        $client->request('GET', '/communications/new');
        $client->submitForm('Guardar', [
            'communication[occurredOn]' => '2026-03-12',
            'communication[scope]' => CommunicationScope::EXTERNAL->value,
            'communication[category]' => CommunicationCategory::COMPLAINT->value,
            'communication[channel]' => CommunicationChannel::EMAIL->value,
            'communication[subject]' => 'Queja por retraso en la retirada',
            'communication[interestedParty]' => (string) $party->getId(),
        ]);

        self::assertResponseRedirects('/communications');

        $all = static::getContainer()->get(CommunicationRepository::class)->findAllOrdered();
        self::assertCount(1, $all);
        $communication = $all[0];
        self::assertTrue($communication->isComplaint());
        $linkedParty = $communication->getInterestedParty();
        self::assertNotNull($linkedParty);
        self::assertSame('Gestores de residuos', $linkedParty->getName());
    }

    public function testSubmittingInvalidCommunicationRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/communications/new');

        // Blank required subject: the form must be re-rendered with errors, not persisted.
        $client->submitForm('Guardar', [
            'communication[occurredOn]' => '2026-03-12',
            'communication[scope]' => CommunicationScope::INTERNAL->value,
            'communication[category]' => CommunicationCategory::INFORMATION->value,
            'communication[channel]' => CommunicationChannel::MEETING->value,
            'communication[subject]' => '',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
        self::assertCount(0, static::getContainer()->get(CommunicationRepository::class)->findAllOrdered());
    }

    public function testDeletingCommunicationRemovesItAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $communication = (new Communication())
            ->setOccurredOn(new \DateTimeImmutable('2026-03-12'))
            ->setScope(CommunicationScope::EXTERNAL)
            ->setCategory(CommunicationCategory::COMPLAINT)
            ->setChannel(CommunicationChannel::EMAIL)
            ->setSubject('Queja a eliminar');
        $em->persist($communication);
        $em->flush();
        $id = $communication->getId();

        $client->request('GET', '/communications');
        $client->submitForm('Eliminar');

        self::assertResponseRedirects('/communications');
        self::assertNull(static::getContainer()->get(CommunicationRepository::class)->find($id));

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'communication.deleted'])
        );
    }
}
