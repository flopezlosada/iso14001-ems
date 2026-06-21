<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the supplier UI. Routes require write access to the
 * supplier area; each test logs in a user that has it. Database writes are rolled back after each
 * test by DAMA DoctrineTestBundle.
 */
final class SupplierControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('compras')->setName('Gestión de proveedores')->setLevel(Area::SUPPLIER, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('supplier-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/suppliers');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Proveedores');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/suppliers/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input#supplier_name');
    }

    public function testSubmittingValidSupplierPersistsItAndRedirectsToDetail(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/suppliers/new');
        $client->submitForm('Guardar', [
            'supplier[name]' => 'Repsol Comercial de Productos Petrolíferos',
            'supplier[productOrService]' => 'Gasoil',
        ]);

        $supplier = static::getContainer()->get(SupplierRepository::class)->findOneBy([]);
        self::assertNotNull($supplier);
        self::assertResponseRedirects('/suppliers/'.$supplier->getId());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'supplier.created'])
        );
    }

    public function testSubmittingWithoutNameRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/suppliers/new');
        $client->submitForm('Guardar', [
            'supplier[name]' => '',
            'supplier[productOrService]' => 'Papel',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
