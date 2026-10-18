<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;
use App\Repository\ConsumptionReadingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the yearly per-utility totals that close the F-6.1.2 grid, over a real test
 * database (rolled back per test). Includes the real register's shapes: readings without a cost and
 * utilities that carry no cost at all.
 */
final class ConsumptionReadingRepositoryTest extends KernelTestCase
{
    private ConsumptionReadingRepository $readings;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->readings = $container->get(ConsumptionReadingRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    private function persistReading(ConsumptionType $type, int $year, int $month, string $quantity, ?string $cost): void
    {
        $this->entityManager->persist(
            (new ConsumptionReading())
                ->setType($type)
                ->setPeriodYear($year)
                ->setPeriodMonth($month)
                ->setQuantity($quantity)
                ->setCost($cost),
        );
    }

    public function testSumsQuantityAndCostPerUtilityForTheYearOnly(): void
    {
        $this->persistReading(ConsumptionType::GASOIL, 2026, 1, '8700', '7524.03');
        $this->persistReading(ConsumptionType::GASOIL, 2026, 2, '4500.5', '4443.31');
        // A reading recorded without its invoice yet: counts in the quantity, not in the cost.
        $this->persistReading(ConsumptionType::GASOIL, 2026, 3, '3000', null);
        $this->persistReading(ConsumptionType::GASOIL, 2025, 1, '5000', '5199.94');
        $this->persistReading(ConsumptionType::INK_CARTRIDGE, 2026, 1, '12', null);
        $this->entityManager->flush();

        $totals = $this->readings->totalsForYear(2026);

        self::assertEqualsCanonicalizing(['gasoil', 'ink_cartridge'], array_keys($totals));
        self::assertSame('16200.500', $totals['gasoil']['quantity']);
        self::assertSame('11967.34', $totals['gasoil']['cost']);
        self::assertSame('12.000', $totals['ink_cartridge']['quantity']);
        self::assertNull($totals['ink_cartridge']['cost']);
    }

    public function testAYearWithoutReadingsHasNoTotals(): void
    {
        $this->persistReading(ConsumptionType::WATER, 2025, 4, '255', '381.39');
        $this->entityManager->flush();

        self::assertSame([], $this->readings->totalsForYear(2026));
    }
}
