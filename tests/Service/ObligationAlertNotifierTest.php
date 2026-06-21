<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Document;
use App\Entity\Role;
use App\Entity\ScheduledAlert;
use App\Entity\User;
use App\Enum\AlertFrequency;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Repository\ScheduledAlertRepository;
use App\Service\ObligationAlertNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test of the alert notifier: only due, non-event, not-yet-notified alerts are sent,
 * and they get stamped so they are not re-sent in the same cycle.
 */
final class ObligationAlertNotifierTest extends KernelTestCase
{
    public function testNotifiesOnlyDueAlertsAndStampsThem(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('secretary')->setName('Secretaría');
        $em->persist($role);
        $user = (new User())->setFullName('Ana Secretaría')->setEmail('ana-alert@example.test')->setActive(true);
        $user->addAssignedRole($role);
        $em->persist($user);

        $document = (new Document())
            ->setCode('TEST-ALERT')
            ->setTitle('Obligación de prueba')
            ->setType(DocumentType::FORM)
            ->setIsoChapter(IsoChapter::PLANNING);

        $dueAlert = (new ScheduledAlert())->setFrequency(AlertFrequency::MONTHLY)->setNextDueDate(new \DateTimeImmutable('2000-01-01'));
        $dueAlert->addRecipientRole($role);
        $futureAlert = (new ScheduledAlert())->setFrequency(AlertFrequency::MONTHLY)->setNextDueDate(new \DateTimeImmutable('2999-01-01'));
        $futureAlert->addRecipientRole($role);
        $document->addAlert($dueAlert);
        $document->addAlert($futureAlert);
        $em->persist($document);
        $em->flush();

        $summary = self::getContainer()->get(ObligationAlertNotifier::class)->notify(new \DateTimeImmutable('today'));

        self::assertSame(1, $summary['alerts'], 'only the overdue alert is due');
        self::assertSame(1, $summary['emails'], 'the single recipient gets one digest e-mail');
        self::assertNotNull($dueAlert->getLastNotifiedAt(), 'the due alert is stamped as notified');
        self::assertNull($futureAlert->getLastNotifiedAt(), 'the future alert is left untouched');
    }

    public function testEscalatedAlertWithoutRecipientIsCountedNotStamped(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // A due alert whose recipient role has no active user: nobody to mail, so it must not be
        // stamped (it should be retried once a user is assigned).
        $orphanRole = (new Role())->setCode('cfpg')->setName('CFGS');
        $em->persist($orphanRole);

        $document = (new Document())->setCode('TEST-ORPHAN')->setTitle('Sin destinatario')->setType(DocumentType::FORM)->setIsoChapter(IsoChapter::OPERATION);
        $alert = (new ScheduledAlert())->setFrequency(AlertFrequency::ANNUAL)->setNextDueDate(new \DateTimeImmutable('2000-01-01'));
        $alert->addRecipientRole($orphanRole);
        $document->addAlert($alert);
        $em->persist($document);
        $em->flush();

        $summary = self::getContainer()->get(ObligationAlertNotifier::class)->notify(new \DateTimeImmutable('today'));

        self::assertSame(1, $summary['withoutRecipient']);
        self::assertSame(0, $summary['emails']);
        self::assertNull($alert->getLastNotifiedAt(), 'an alert with no recipient is not stamped');
    }
}
