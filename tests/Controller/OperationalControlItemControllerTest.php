<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\OperationalControlSection;
use App\Enum\PermissionLevel;
use App\Repository\OperationalControlItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the admin management of the operational-control checklist catalogue: render,
 * create an item, and the ROLE_ADMIN gate. Rolled back after each test by DAMA.
 */
final class OperationalControlItemControllerTest extends WebTestCase
{
    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('admin')->setName('Administrador')->setAdmin(true);
        $em->persist($role);
        $user = (new User())->setFullName('Admin')->setEmail('checklist-admin@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testIndexRendersForAdmin(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/checklist-control-operacional');

        self::assertResponseIsSuccessful();
    }

    public function testCreateItemPersists(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/admin/checklist-control-operacional/nuevo');
        $client->submitForm('Guardar', [
            'operational_control_item[section]' => OperationalControlSection::WATER->value,
            'operational_control_item[label]' => 'Ítem de prueba',
            'operational_control_item[position]' => '5',
            'operational_control_item[active]' => '1',
        ]);

        self::assertResponseRedirects('/admin/checklist-control-operacional');

        $items = static::getContainer()->get(OperationalControlItemRepository::class)->findBy(['label' => 'Ítem de prueba']);
        self::assertCount(1, $items);
        self::assertSame(OperationalControlSection::WATER, $items[0]->getSection());
    }

    public function testRequiresAdmin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('plain')->setName('Usuario')->setLevel(Area::OPERATIONAL_CONTROL, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Pepe')->setEmail('checklist-plain@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/admin/checklist-control-operacional');

        self::assertResponseStatusCodeSame(403);
    }
}
