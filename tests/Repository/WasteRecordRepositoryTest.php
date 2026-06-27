<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\WasteRecord;
use App\Repository\WasteRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the yearly waste aggregation that feeds the multi-year trend, over a real
 * test database (rolled back per test). The fixtures deliberately include the messy shapes the real
 * register holds — records with no pick-up date and records with no weight — to prove they are
 * skipped instead of breaking the aggregation.
 */
final class WasteRecordRepositoryTest extends KernelTestCase
{
    private WasteRecordRepository $records;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->records = $container->get(WasteRecordRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Persists a waste record with sensible defaults, overridable per field.
     */
    private function persistRecord(
        ?string $quantityKg,
        ?string $pickupDate,
        bool $hazardous = false,
        string $description = 'Residuo de prueba',
    ): void {
        $record = (new WasteRecord())
            ->setDescription($description)
            ->setQuantityKg($quantityKg)
            ->setHazardous($hazardous)
            ->setPickupDate(null === $pickupDate ? null : new \DateTimeImmutable($pickupDate));

        $this->entityManager->persist($record);
    }

    public function testSumsKilogramsPerYearAscending(): void
    {
        $this->persistRecord('100.500', '2024-03-10');
        $this->persistRecord('200', '2024-11-02');
        $this->persistRecord('50', '2026-01-15');
        $this->entityManager->flush();

        // 100.500 + 200 = 300.5 in 2024; 50 in 2026. Years ascending, with the decimal kept.
        self::assertSame(
            [2024 => 300.5, 2026 => 50.0],
            $this->records->yearlyTotalsKg(),
        );
    }

    public function testSkipsRecordsWithoutPickupDateOrWeight(): void
    {
        $this->persistRecord('100', '2025-05-01');
        // No pick-up date (real register: free-text month/range kept in notes): year unknown.
        $this->persistRecord('999', null);
        // No weight (real register: amount in "bolsones"): cannot be summed in kg.
        $this->persistRecord(null, '2025-07-01');
        $this->entityManager->flush();

        // Only the one weighed, dated record counts; the year is not inflated by the others.
        self::assertSame([2025 => 100.0], $this->records->yearlyTotalsKg());
    }

    public function testFiltersByHazardousFlag(): void
    {
        $this->persistRecord('300', '2025-02-01', hazardous: true);
        $this->persistRecord('700', '2025-09-01', hazardous: false);
        $this->entityManager->flush();

        self::assertSame([2025 => 1000.0], $this->records->yearlyTotalsKg());
        self::assertSame([2025 => 300.0], $this->records->yearlyTotalsKg(true));
        self::assertSame([2025 => 700.0], $this->records->yearlyTotalsKg(false));
    }

    public function testReturnsEmptyWhenNoUsableRecords(): void
    {
        $this->persistRecord(null, null);
        $this->entityManager->flush();

        self::assertSame([], $this->records->yearlyTotalsKg());
    }
}
