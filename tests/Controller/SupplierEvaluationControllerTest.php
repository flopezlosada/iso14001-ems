<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\SupplierCriterion;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for supplier yearly evaluations, nested under a supplier.
 * Database writes are rolled back after each test by DAMA DoctrineTestBundle.
 */
final class SupplierEvaluationControllerTest extends WebTestCase
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
        $user->setFullName('Tester')->setEmail('seval-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $supplier = (new Supplier())->setName('Ediciones Lumar S.L.')->setProductOrService('Papel');
        $em->persist($supplier);

        $em->flush();
        $client->loginUser($user);

        return [$client, $supplier->getId()];
    }

    public function testNewEvaluationFormRenders(): void
    {
        [$client, $supplierId] = $this->scenario();
        $client->request('GET', '/suppliers/'.$supplierId.'/evaluations/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#supplier_evaluation_criterion');
    }

    public function testSubmittingEvaluationPersistsItAndRedirectsToSupplier(): void
    {
        [$client, $supplierId] = $this->scenario();
        $client->request('GET', '/suppliers/'.$supplierId.'/evaluations/new');
        $client->submitForm('Guardar', [
            'supplier_evaluation[year]' => '2026',
            'supplier_evaluation[criterion]' => 'on_trial',
        ]);

        self::assertResponseRedirects('/suppliers/'.$supplierId);

        $supplier = static::getContainer()->get(SupplierRepository::class)->find($supplierId);
        self::assertNotNull($supplier);
        $latest = $supplier->getLatestEvaluation();
        self::assertNotNull($latest);
        self::assertSame(2026, $latest->getYear());
        self::assertSame(SupplierCriterion::ON_TRIAL, $latest->getCriterion());
        // The status is derived from the criterion (on trial → approved).
        self::assertTrue($latest->isApproved());
    }

    public function testLatestEvaluationIsTheMostRecentYear(): void
    {
        [$client, $supplierId] = $this->scenario();

        foreach ([['2025', 'capable'], ['2026', 'not_capable']] as [$year, $criterion]) {
            $client->request('GET', '/suppliers/'.$supplierId.'/evaluations/new');
            $client->submitForm('Guardar', [
                'supplier_evaluation[year]' => $year,
                'supplier_evaluation[criterion]' => $criterion,
            ]);
        }

        $supplier = static::getContainer()->get(SupplierRepository::class)->find($supplierId);
        self::assertNotNull($supplier);
        $latest = $supplier->getLatestEvaluation();
        self::assertNotNull($latest);
        self::assertSame(2026, $latest->getYear());
        self::assertFalse($latest->isApproved());
    }
}
