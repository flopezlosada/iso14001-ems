<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ConsumptionReading;
use App\Entity\EnvironmentalAspect;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\ConsumptionType;
use App\Service\AspectTrendNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test of the aspect-trend notifier: it e-mails the SGA manager when an aspect's data is
 * trending up beyond the threshold, and stays silent when nothing is to watch.
 */
final class AspectTrendNotifierTest extends KernelTestCase
{
    public function testSendsDigestToTheManagerWhenAnAspectTrendsUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('ems_manager')->setName('Responsable SGMA');
        $em->persist($role);
        $user = (new User())->setFullName('Gestor SGA')->setEmail('trend-manager@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $aspect = (new EnvironmentalAspect())->setName('Consumo eléctrico')->setLinkedConsumptionType(ConsumptionType::ELECTRICITY);
        $em->persist($aspect);

        // +30% in the same month year-on-year → HIGH → watchworthy.
        foreach ([[2025, '1000'], [2026, '1300']] as [$year, $quantity]) {
            $em->persist(
                (new ConsumptionReading())
                    ->setType(ConsumptionType::ELECTRICITY)
                    ->setPeriodYear($year)
                    ->setPeriodMonth(1)
                    ->setQuantity($quantity),
            );
        }
        $em->flush();

        $summary = self::getContainer()->get(AspectTrendNotifier::class)->notify(new \DateTimeImmutable('2026-06-15'));

        self::assertSame(1, $summary['watched']);
        self::assertSame(1, $summary['emails']);
    }

    public function testSendsNothingWhenNoAspectTrendsUp(): void
    {
        self::bootKernel();

        // No linked aspects with data → nothing to watch, no e-mail.
        $summary = self::getContainer()->get(AspectTrendNotifier::class)->notify(new \DateTimeImmutable('2026-06-15'));

        self::assertSame(0, $summary['watched']);
        self::assertSame(0, $summary['emails']);
    }

    public function testDetectsTrendButSendsNothingWhenNoManagerIsAssigned(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Same +30% trend as the happy path, but no active user holds the manager role.
        $aspect = (new EnvironmentalAspect())->setName('Consumo eléctrico')->setLinkedConsumptionType(ConsumptionType::ELECTRICITY);
        $em->persist($aspect);
        foreach ([[2025, '1000'], [2026, '1300']] as [$year, $quantity]) {
            $em->persist(
                (new ConsumptionReading())
                    ->setType(ConsumptionType::ELECTRICITY)
                    ->setPeriodYear($year)
                    ->setPeriodMonth(1)
                    ->setQuantity($quantity),
            );
        }
        $em->flush();

        $summary = self::getContainer()->get(AspectTrendNotifier::class)->notify(new \DateTimeImmutable('2026-06-15'));

        self::assertSame(1, $summary['watched'], 'the trend is detected');
        self::assertSame(0, $summary['emails'], 'but there is nobody to notify');
    }
}
