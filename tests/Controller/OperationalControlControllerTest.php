<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\OperationalControlItem;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\OperationalControlSection;
use App\Enum\PermissionLevel;
use App\Repository\OperationalControlCheckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) tests for the operational-control module: list, fill in the monthly checklist
 * from the catalogue, and the per-area permission gate. Rolled back after each test by DAMA.
 */
final class OperationalControlControllerTest extends WebTestCase
{
    /**
     * Logs in a user with the given permission level on Area::OPERATIONAL_CONTROL and seeds a couple
     * of catalogue items so the checklist has rows.
     */
    private function scenario(PermissionLevel $level = PermissionLevel::WRITE): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('control-op')->setName('Control operacional')->setLevel(Area::OPERATIONAL_CONTROL, $level);
        $em->persist($role);
        $user = (new User())->setFullName('Inspector')->setEmail('opcontrol-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $em->persist((new OperationalControlItem())->setSection(OperationalControlSection::WATER)->setLabel('Grifos cerrados tras su uso')->setPosition(0)->setActive(true));
        $em->persist((new OperationalControlItem())->setSection(OperationalControlSection::ENERGY)->setLabel('Luces apagadas al salir')->setPosition(1)->setActive(true));

        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->scenario(PermissionLevel::READ);
        $client->request('GET', '/operational-control');

        self::assertResponseIsSuccessful();
    }

    public function testNewBuildsTheChecklistFromTheCatalogue(): void
    {
        $client = $this->scenario();
        $client->request('GET', '/operational-control/new');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Grifos cerrados tras su uso', $html);
        self::assertStringContainsString('Luces apagadas al salir', $html);
    }

    public function testCreateMonthlyCheckPersistsAnswersForEveryItem(): void
    {
        $client = $this->scenario();
        $client->request('GET', '/operational-control/new');
        $client->submitForm('Guardar', [
            'operational_control_check[periodYear]' => '2026',
            'operational_control_check[periodMonth]' => '6',
            'operational_control_check[performedBy]' => 'Equipo de limpieza',
        ]);

        self::assertResponseRedirects('/operational-control');

        $check = static::getContainer()->get(OperationalControlCheckRepository::class)->findOneByPeriod(2026, 6);
        self::assertNotNull($check);
        // One (unanswered) row per catalogue item was pre-filled and saved.
        self::assertCount(2, $check->getAnswers());
    }

    public function testWriteRequiresPermission(): void
    {
        $client = $this->scenario(PermissionLevel::READ);
        $client->request('GET', '/operational-control/new');

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexRequiresReadPermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // A user with access to a different area, but none on operational control.
        $role = (new Role())->setCode('otra-area')->setName('Otra área')->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Sin acceso')->setEmail('opcontrol-noaccess@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/operational-control');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDuplicateMonthRedisplaysTheFormInsteadOfCrashing(): void
    {
        $client = $this->scenario();

        // Two inspections for the same month: the second must be rejected gracefully (form
        // redisplayed by the UniqueEntity constraint), not a 500 from the DB unique index.
        foreach ([1, 2] as $attempt) {
            $client->request('GET', '/operational-control/new');
            $client->submitForm('Guardar', [
                'operational_control_check[periodYear]' => '2026',
                'operational_control_check[periodMonth]' => '6',
                'operational_control_check[performedBy]' => 'Inspector '.$attempt,
            ]);
        }

        self::assertFalse($client->getResponse()->isRedirect(), 'the duplicate must not be saved');
        // Symfony re-renders an invalid form with 422 (Unprocessable Content), not 200; assert that
        // exact behaviour instead of a generic 2xx, consistent with the rest of the controllers.
        self::assertResponseStatusCodeSame(422);
    }
}
