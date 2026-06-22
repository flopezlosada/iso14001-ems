<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ConsumptionReading;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\ConsumptionType;
use App\Enum\PermissionLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test of the multi-year consumption trend view: it renders the yearly bars for a reader,
 * and the per-area READ permission gates it.
 */
final class ConsumptionTrendControllerTest extends WebTestCase
{
    public function testTrendRendersYearlyBars(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('consumos')->setName('Consumos')->setLevel(Area::CONSUMPTION, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Lectora')->setEmail('trend-reader@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        foreach ([[2024, '900'], [2025, '1000'], [2026, '1300']] as [$year, $quantity]) {
            $em->persist(
                (new ConsumptionReading())
                    ->setType(ConsumptionType::ELECTRICITY)
                    ->setPeriodYear($year)
                    ->setPeriodMonth(1)
                    ->setQuantity($quantity),
            );
        }
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/consumption/tendencia');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Tendencia de consumos', $html);
        self::assertStringContainsString('2024', $html);
        self::assertStringContainsString('2026', $html);
    }

    public function testShowsEmptyStateWhenThereIsNoData(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('consumos')->setName('Consumos')->setLevel(Area::CONSUMPTION, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Lectora')->setEmail('trend-empty@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/consumption/tendencia');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aún no hay consumos', (string) $client->getResponse()->getContent());
    }

    public function testRequiresReadPermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('otra')->setName('Otra área')->setLevel(Area::WASTE, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Sin acceso')->setEmail('trend-noaccess@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/consumption/tendencia');

        self::assertResponseStatusCodeSame(403);
    }
}
