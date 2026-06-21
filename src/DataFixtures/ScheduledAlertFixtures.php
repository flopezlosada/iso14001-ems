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
 * The review cadence of each obligation (the "REVISIÓN" column of the register). Part of the GOLDEN
 * backbone: the periodicity is structural, not sample data — it is what drives the e-mail alert
 * engine and the "Qué toca" view.
 *
 * Due dates are seeded around mid-2026 on purpose to exercise the traffic-light: some are overdue
 * (red), some fall due this month (amber) and some are comfortably ahead (green). Documents with a
 * double cadence (F.08.0, F.11.0) carry two alerts; event-driven obligations (fichas de seguridad,
 * comunicación a proveedores, archivo de residuos) use ON_EVENT, which has no fixed roll-forward.
 */
final class ScheduledAlertFixtures extends AbstractGoldenFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // [document code, frequency, next due date, recipient role codes, escalation days|null]
        $alerts = [
            // 00.PLAN
            ['DO-04.02', AlertFrequency::ANNUAL, '2026-09-01', ['direction'], 15],
            ['F.06.0', AlertFrequency::ANNUAL, '2026-09-01', ['direction'], 15],
            ['F.04.0', AlertFrequency::ANNUAL, '2026-09-01', ['direction'], 15],
            ['PA.01.0', AlertFrequency::ANNUAL, '2026-01-08', ['direction', 'ems_manager'], 15], // overdue: pending signature
            ['ORG-05.01', AlertFrequency::ANNUAL, '2026-09-01', ['direction'], 15],
            ['F.07.01', AlertFrequency::BIANNUAL, '2026-07-01', ['ems_manager'], 7],
            ['F.07.0', AlertFrequency::BIANNUAL, '2026-07-01', ['ems_manager'], 7],
            ['RL-06.03', AlertFrequency::BIANNUAL, '2026-07-01', ['ems_manager'], 7], // due soon: pending extraction
            ['RG-06.01.01', AlertFrequency::ANNUAL, '2026-11-01', ['ems_manager'], 15],
            ['F-6.1.2', AlertFrequency::MONTHLY, '2026-06-30', ['secretary'], null],
            ['F-6.1.3', AlertFrequency::MONTHLY, '2026-06-30', ['secretary'], null],
            ['F.08.0', AlertFrequency::ANNUAL, '2026-12-01', ['direction'], 15],
            ['F.08.0', AlertFrequency::BIANNUAL, '2026-07-01', ['direction'], 7], // double cadence: semestral review
            // 01.IMPLEMENTACIÓN
            ['RG-07.04.00', AlertFrequency::ANNUAL, '2026-10-01', ['ems_manager'], 15],
            ['RG-07.01.01', AlertFrequency::ANNUAL, '2027-02-26', ['cfpg'], 15],
            ['F.03.0', AlertFrequency::ANNUAL, '2026-06-30', ['ems_manager'], 15], // the plan is made in June
            ['F.0X.0', AlertFrequency::ANNUAL, '2026-09-01', ['ems_manager'], 15],
            ['RG-07.02', AlertFrequency::BIANNUAL, '2026-09-01', ['ems_manager'], 7],
            ['OCA-08.01', AlertFrequency::ANNUAL, '2027-03-01', ['ems_manager'], 15],
            ['RG-08.01.01', AlertFrequency::MONTHLY, '2026-05-31', ['cleaning'], null], // overdue
            ['RG-08.02.01-I', AlertFrequency::ANNUAL, '2026-12-01', ['ems_manager'], 15],
            ['RG-08.02.01-C', AlertFrequency::ANNUAL, '2027-02-01', ['ems_manager'], 15],
            ['FS-08.03', AlertFrequency::ON_EVENT, '2026-06-21', ['ems_manager'], null],
            ['RG-08.04', AlertFrequency::BIANNUAL, '2026-09-01', ['cfpg'], 7],
            ['RG-08.05', AlertFrequency::ANNUAL, '2026-09-01', ['cfpg'], 15],
            ['F.12.0', AlertFrequency::ANNUAL, '2027-02-01', ['ems_manager'], 15],
            ['F.12.1', AlertFrequency::ON_EVENT, '2026-06-21', ['ems_manager'], null],
            ['RG-08.06', AlertFrequency::ON_EVENT, '2026-06-21', ['ems_manager'], null],
            // 02.VERIFICAR
            ['RG-09.03.01', AlertFrequency::ANNUAL, '2026-10-01', ['direction', 'ems_manager'], 15],
            ['F.09.0', AlertFrequency::MONTHLY, '2026-06-30', ['ems_manager'], null],
            ['AUD-09.02', AlertFrequency::ANNUAL, '2026-10-15', ['ems_manager', 'direction'], 30], // external audit anchor
            // 03.MEJORA
            ['RG-08.07.01', AlertFrequency::ANNUAL, '2027-02-01', ['ems_manager'], 15],
            ['F.11.0', AlertFrequency::ANNUAL, '2026-12-01', ['ems_manager'], 15],
            ['F.11.0', AlertFrequency::MONTHLY, '2026-06-30', ['ems_manager'], null], // double cadence: monthly review
            ['F.10.0', AlertFrequency::MONTHLY, '2026-06-30', ['ems_manager'], null],
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
