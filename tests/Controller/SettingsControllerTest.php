<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the business-settings screen: render and save for an admin, and the
 * ROLE_ADMIN gate. Rolled back after each test by DAMA.
 */
final class SettingsControllerTest extends WebTestCase
{
    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('admin')->setName('Administrador')->setAdmin(true);
        $em->persist($role);
        $user = (new User())->setFullName('Admin')->setEmail('settings-admin@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testEditRendersForAdmin(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/settings');

        self::assertResponseIsSuccessful();
    }

    public function testSaveUpdatesTheConfiguration(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/settings');
        $client->submitForm('Guardar', [
            'settings[consumptionThreshold]' => '14',
            'settings[emissionThreshold]' => '12',
            'settings[wasteThreshold]' => '10',
            'settings[dischargeThreshold]' => '8',
            'settings[abnormalThreshold]' => '10',
            'settings[intensityRiseThreshold]' => '15', // 15% → 0.15
            'settings[intensityDropThreshold]' => '10',
            'settings[intensityBaselineYears]' => '2',
        ]);

        self::assertResponseRedirects('/admin/settings');

        $settings = static::getContainer()->get(SettingsRepository::class)->findSettings();
        self::assertNotNull($settings);
        self::assertSame(14, $settings->getConsumptionThreshold());
        self::assertSame(2, $settings->getIntensityBaselineYears());
        self::assertEqualsWithDelta(0.15, $settings->getIntensityRiseThreshold(), 0.0001);
    }

    public function testRequiresAdmin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('plain')->setName('Usuario')->setLevel(Area::CONSUMPTION, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Pepe')->setEmail('settings-plain@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/admin/settings');

        self::assertResponseStatusCodeSame(403);
    }
}
