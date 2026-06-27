<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\WasteRecord;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test of the multi-year waste trend view: it renders the yearly bars for a reader, and
 * the per-area READ permission gates it.
 */
final class WasteTrendControllerTest extends WebTestCase
{
    public function testTrendRendersYearlyBars(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('residuos')->setName('Residuos')->setLevel(Area::WASTE, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Lectora')->setEmail('waste-trend-reader@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        foreach ([['2024-04-01', '500', false], ['2025-06-01', '800', true], ['2026-02-01', '1200', false]] as [$date, $kg, $hazardous]) {
            $em->persist(
                (new WasteRecord())
                    ->setDescription('Residuo')
                    ->setQuantityKg($kg)
                    ->setHazardous($hazardous)
                    ->setPickupDate(new \DateTimeImmutable($date)),
            );
        }
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/waste/tendencia');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Tendencia de residuos', $html);
        self::assertStringContainsString('Total de residuos', $html);
        // The hazardous breakdown series render because there is at least one record of each kind.
        self::assertStringContainsString('Residuos peligrosos', $html);
        self::assertStringContainsString('Residuos no peligrosos', $html);
        self::assertStringContainsString('2024', $html);
        self::assertStringContainsString('2026', $html);
    }

    public function testShowsEmptyStateWhenThereIsNoData(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('residuos')->setName('Residuos')->setLevel(Area::WASTE, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Lectora')->setEmail('waste-trend-empty@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/waste/tendencia');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aún no hay residuos', (string) $client->getResponse()->getContent());
    }

    public function testRequiresReadPermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('otra')->setName('Otra área')->setLevel(Area::CONSUMPTION, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Sin acceso')->setEmail('waste-trend-noaccess@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/waste/tendencia');

        self::assertResponseStatusCodeSame(403);
    }
}
