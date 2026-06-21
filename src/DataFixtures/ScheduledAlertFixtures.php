<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Document;
use App\Entity\Role;
use App\Entity\ScheduledAlert;
use App\Enum\AlertFrequency;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Scheduled review reminders attached to documents (PC.01.0 keeps documented information current).
 * Sample DEMO data: the e-mail alerting is a core deliverable, so the seed exercises several
 * frequencies and recipient roles, plus an overdue and an escalating alert.
 */
final class ScheduledAlertFixtures extends AbstractDemoFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // [document code, frequency, next due date, recipient role codes, escalation days|null]
        $alerts = [
            ['PA.01.0', AlertFrequency::ANNUAL, '2026-01-08', ['direction', 'ems_manager'], 15],
            ['F.01.0', AlertFrequency::ANNUAL, '2026-01-08', ['ems_manager'], null],
            ['PC.10.0', AlertFrequency::BIANNUAL, '2025-12-01', ['quality'], 7],
            ['F.03.0', AlertFrequency::ANNUAL, '2026-09-01', ['secretary'], null],
        ];

        foreach ($alerts as [$documentCode, $frequency, $nextDue, $roleCodes, $escalationDays]) {
            $alert = new ScheduledAlert();
            $alert->setDocument($this->getReference(DocumentFixtures::ref($documentCode), Document::class))
                ->setFrequency($frequency)
                ->setNextDueDate(new \DateTimeImmutable($nextDue))
                ->setEscalationDays($escalationDays);
            foreach ($roleCodes as $code) {
                $alert->addRecipientRole($this->getReference(RoleFixtures::ref($code), Role::class));
            }
            $manager->persist($alert);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [DocumentFixtures::class, RoleFixtures::class];
    }
}
