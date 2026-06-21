<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\EmergencyDrillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) tests for the emergency drill register (RG-08.02.01), including per-area
 * authorization. Database writes are rolled back after each test by DAMA DoctrineTestBundle.
 */
final class EmergencyDrillControllerTest extends WebTestCase
{
    private function clientWithEmergencyWrite(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = (new Role())->setCode('simulacros')->setName('Gestión de simulacros')->setLevel(Area::EMERGENCY, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Tester')->setEmail('simulacros-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    private function clientWithEmergencyReadOnly(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = (new Role())->setCode('simulacros-lectura')->setName('Lectura de simulacros')->setLevel(Area::EMERGENCY, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Solo lectura')->setEmail('simulacros-lectura@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testListIsForbiddenWithoutPermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setFullName('Sin permiso')->setEmail('noperm-drill@example.test')->setActive(true);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/emergency-drills');
        self::assertResponseStatusCodeSame(403);
    }

    public function testReadOnlyCanListButNotCreate(): void
    {
        $client = $this->clientWithEmergencyReadOnly();

        $client->request('GET', '/emergency-drills');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Simulacros');

        $client->request('GET', '/emergency-drills/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSubmittingValidDrillPersistsItAndRedirects(): void
    {
        $client = $this->clientWithEmergencyWrite();
        $client->request('GET', '/emergency-drills/new');

        // Realistic data: a drill report whose signing author is left blank (stays null).
        $client->submitForm('Guardar', [
            'emergency_drill[drillDate]' => '2025-12-17',
            'emergency_drill[emergencyType]' => 'Simulacro incendio',
            'emergency_drill[location]' => 'Instituto',
            'emergency_drill[participants]' => 'Resp. mantenimiento, RSGM, técnico ambiental, dirección',
            'emergency_drill[actionProcedure]' => 'Evacuación según el protocolo del centro; residuo de cenizas comunicado al gestor.',
            'emergency_drill[conclusions]' => 'Duración 4 minutos, resultado satisfactorio.',
            'emergency_drill[reportedBy]' => '',
        ]);

        self::assertResponseRedirects('/emergency-drills');

        $drills = static::getContainer()->get(EmergencyDrillRepository::class)->findRecent();
        self::assertCount(1, $drills);
        $drill = $drills[0];
        self::assertSame('Simulacro incendio', $drill->getEmergencyType());
        // The optional signing author was left empty.
        self::assertNull($drill->getReportedBy());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'emergency_drill.created'])
        );

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Simulacro incendio');
    }

    public function testSubmittingInvalidDrillRedisplaysFormWithErrors(): void
    {
        $client = $this->clientWithEmergencyWrite();
        $client->request('GET', '/emergency-drills/new');

        // Blank required text fields (drill date kept valid): the form must be re-rendered with
        // errors, not persisted.
        $client->submitForm('Guardar', [
            'emergency_drill[drillDate]' => '2025-12-17',
            'emergency_drill[emergencyType]' => '',
            'emergency_drill[location]' => '',
            'emergency_drill[participants]' => '',
            'emergency_drill[actionProcedure]' => '',
            'emergency_drill[conclusions]' => '',
        ]);

        // An invalid submission re-renders the form (Symfony returns HTTP 422, not a redirect).
        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
        self::assertCount(0, static::getContainer()->get(EmergencyDrillRepository::class)->findRecent());
    }
}
