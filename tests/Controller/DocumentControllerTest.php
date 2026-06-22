<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Document;
use App\Entity\Role;
use App\Entity\ScheduledAlert;
use App\Entity\User;
use App\Enum\AlertFrequency;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the obligations cockpit: the "Qué toca" (urgency, with the Mías/Todas scope)
 * and "Estructura SGA" (PDCA) views.
 */
final class DocumentControllerTest extends WebTestCase
{
    private function persistObligation(EntityManagerInterface $em, string $code, IsoChapter $chapter, AlertFrequency $frequency, string $nextDue, ?Role $role = null): void
    {
        $document = new Document();
        $document->setCode($code)
            ->setTitle('Obligación '.$code)
            ->setType(DocumentType::FORM)
            ->setIsoChapter($chapter)
            ->setStatus(ObligationStatus::PENDING)
            ->setResponsibleRole($role);

        $alert = new ScheduledAlert();
        $alert->setFrequency($frequency)->setNextDueDate(new \DateTimeImmutable($nextDue));
        $document->addAlert($alert);

        $em->persist($document);
    }

    private function loginPlainUser(KernelBrowser $client): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setFullName('Lectora')->setEmail('lectora@example.test')->setActive(true);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);
    }

    private function loginUserWithRole(KernelBrowser $client, Role $role): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($role);
        $user = new User();
        $user->setFullName('Responsable')->setEmail('responsable@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/obligaciones');

        self::assertResponseRedirects('/login');
    }

    public function testTodasScopeShowsOverdueObligation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // An overdue monthly obligation: under "todas" it must land in the "Vencido" bucket.
        $this->persistObligation($em, 'TEST-OVERDUE', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01');
        $em->flush();
        $this->loginPlainUser($client);

        $client->request('GET', '/obligaciones?scope=todas');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vencido');
        self::assertSelectorTextContains('body', 'Obligación TEST-OVERDUE');
    }

    public function testMineScopeShowsOnlyOwnObligations(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        $other = (new Role())->setCode('sec')->setName('Secretaría');
        $em->persist($other);
        // One obligation is mine, one belongs to another role.
        $this->persistObligation($em, 'TEST-MINE', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01', $mine);
        $this->persistObligation($em, 'TEST-OTHER', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01', $other);
        $em->flush();
        $this->loginUserWithRole($client, $mine);

        // Default scope is "mías": only the owned obligation shows.
        $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Obligación TEST-MINE');
        self::assertSelectorTextNotContains('body', 'Obligación TEST-OTHER');
    }

    public function testMineScopeEmptyOffersAllWhenNothingOwned(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $other = (new Role())->setCode('sec')->setName('Secretaría');
        $em->persist($other);
        $this->persistObligation($em, 'TEST-OTHER', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01', $other);
        $em->flush();
        // The user holds a role that owns no obligation.
        $this->loginUserWithRole($client, (new Role())->setCode('mant')->setName('Mantenimiento'));

        $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state', 'No tienes obligaciones asignadas');
        self::assertSelectorTextNotContains('body', 'Obligación TEST-OTHER');
    }

    public function testStructureGroupsByPhase(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->persistObligation($em, 'TEST-PLAN', IsoChapter::PLANNING, AlertFrequency::ANNUAL, '2030-01-01');
        $em->flush();
        $this->loginPlainUser($client);

        $client->request('GET', '/sga');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Planificar');
        self::assertSelectorTextContains('body', 'Planificación');
    }
}
