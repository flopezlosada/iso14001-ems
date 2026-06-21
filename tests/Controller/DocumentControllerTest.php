<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Document;
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
 * Functional tests for the obligations cockpit: the "Qué toca" (urgency) and "Estructura SGA"
 * (PDCA) views.
 */
final class DocumentControllerTest extends WebTestCase
{
    private function persistObligation(EntityManagerInterface $em, string $code, IsoChapter $chapter, AlertFrequency $frequency, string $nextDue): void
    {
        $document = new Document();
        $document->setCode($code)
            ->setTitle('Obligación '.$code)
            ->setType(DocumentType::FORM)
            ->setIsoChapter($chapter)
            ->setStatus(ObligationStatus::PENDING);

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

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/obligaciones');

        self::assertResponseRedirects('/login');
    }

    public function testQueTocaShowsOverdueObligation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // An overdue monthly obligation: must land in the "Vencido" bucket.
        $this->persistObligation($em, 'TEST-OVERDUE', IsoChapter::PLANNING, AlertFrequency::MONTHLY, '2000-01-01');
        $em->flush();
        $this->loginPlainUser($client);

        $crawler = $client->request('GET', '/obligaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vencido');
        self::assertSelectorTextContains('body', 'Obligación TEST-OVERDUE');
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
