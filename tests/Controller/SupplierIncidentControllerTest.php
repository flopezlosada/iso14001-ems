<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\SupplierIncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for supplier incidents, nested under a supplier.
 * Database writes are rolled back after each test by DAMA DoctrineTestBundle.
 */
final class SupplierIncidentControllerTest extends WebTestCase
{
    /**
     * @return array{0: KernelBrowser, 1: int} [client, supplierId]
     */
    private function scenario(): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('compras')->setName('Gestión de proveedores')->setLevel(Area::SUPPLIER, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('sinc-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $supplier = (new Supplier())->setName('Hyguilander')->setProductOrService('Productos de limpieza');
        $em->persist($supplier);

        $em->flush();
        $client->loginUser($user);

        return [$client, $supplier->getId()];
    }

    public function testSubmittingIncidentPersistsItAndRedirectsToSupplier(): void
    {
        [$client, $supplierId] = $this->scenario();
        $client->request('GET', '/suppliers/'.$supplierId.'/incidents/new');
        $client->submitForm('Guardar', [
            'supplier_incident[occurredOn]' => '2026-03-10',
            'supplier_incident[description]' => 'Entrega fuera de plazo y producto incompleto.',
        ]);

        self::assertResponseRedirects('/suppliers/'.$supplierId);

        $incident = static::getContainer()->get(SupplierIncidentRepository::class)->findOneBy([]);
        self::assertNotNull($incident);
        self::assertFalse($incident->isSevere());
    }

    public function testSevereIncidentShowsOpenNonConformityLink(): void
    {
        [$client, $supplierId] = $this->scenario();

        // Create the severe incident through the real form flow, then follow back to the detail.
        $client->request('GET', '/suppliers/'.$supplierId.'/incidents/new');
        $client->submitForm('Guardar', [
            'supplier_incident[occurredOn]' => '2026-02-01',
            'supplier_incident[description]' => 'Incumplimiento repetido de requisitos.',
            'supplier_incident[severe]' => '1',
        ]);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        // A severe incident offers a shortcut to open a non-conformity.
        self::assertSelectorExists('a[href*="/non-conformities/new"]');
    }

    public function testSubmittingWithoutDescriptionRedisplaysFormWithErrors(): void
    {
        [$client, $supplierId] = $this->scenario();
        $client->request('GET', '/suppliers/'.$supplierId.'/incidents/new');
        $client->submitForm('Guardar', [
            'supplier_incident[occurredOn]' => '2026-03-10',
            'supplier_incident[description]' => '',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
