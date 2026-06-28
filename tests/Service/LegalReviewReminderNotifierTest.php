<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\LegalRequirement;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\EvaluationFrequency;
use App\Enum\LegalScope;
use App\Service\LegalReviewReminderNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test of the legal-review reminder notifier: it e-mails the SGA manager when a
 * requirement's compliance review is due, and stays silent when none is.
 */
final class LegalReviewReminderNotifierTest extends KernelTestCase
{
    public function testSendsDigestToTheManagerWhenAReviewIsDue(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('ems_manager')->setName('Responsable SGMA');
        $em->persist($role);
        $em->persist(
            (new User())->setFullName('Gestor SGA')->setEmail('legal-manager@example.test')->setActive(true)->addAssignedRole($role),
        );
        // Reviewed 2025-01-01, annual cadence → next review 2026-01-01, overdue at the reference date.
        $em->persist($this->requirement('RL-01', 1, '2025-01-01'));
        $em->flush();

        $summary = self::getContainer()->get(LegalReviewReminderNotifier::class)->notify(new \DateTimeImmutable('2026-06-15'));

        self::assertSame(1, $summary['due']);
        self::assertSame(1, $summary['emails']);
    }

    public function testSendsNothingWhenNoReviewIsDue(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Reviewed 2026-06-01, annual cadence → next review 2027-06-01, far beyond the window.
        $em->persist($this->requirement('RL-01', 1, '2026-06-01'));
        $em->flush();

        $summary = self::getContainer()->get(LegalReviewReminderNotifier::class)->notify(new \DateTimeImmutable('2026-06-15'));

        self::assertSame(0, $summary['due']);
        self::assertSame(0, $summary['emails']);
    }

    public function testReviewDueButSendsNothingWhenNoManagerIsAssigned(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist($this->requirement('RL-01', 1, '2025-01-01'));
        $em->flush();

        $summary = self::getContainer()->get(LegalReviewReminderNotifier::class)->notify(new \DateTimeImmutable('2026-06-15'));

        self::assertSame(1, $summary['due'], 'the due review is detected');
        self::assertSame(0, $summary['emails'], 'but there is nobody to notify');
    }

    private function requirement(string $reference, int $sequence, string $lastReviewed): LegalRequirement
    {
        return (new LegalRequirement())
            ->setReference($reference)
            ->setSequence($sequence)
            ->setLegalProvision('Ley de prueba')
            ->setScope(LegalScope::NATIONAL)
            ->setSpecificRequirement('Requisito de prueba')
            ->setEvaluationFrequency(EvaluationFrequency::ANNUAL)
            ->setLastReviewedOn(new \DateTimeImmutable($lastReviewed));
    }
}
