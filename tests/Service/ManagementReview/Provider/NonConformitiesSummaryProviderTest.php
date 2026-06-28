<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\NonConformity;
use App\Enum\NonConformityOrigin;
use App\Enum\NonConformityStatus;
use App\Repository\NonConformityRepository;
use App\Service\ManagementReview\Provider\NonConformitiesSummaryProvider;
use PHPUnit\Framework\TestCase;

final class NonConformitiesSummaryProviderTest extends TestCase
{
    private function nc(int $year, string $reference, string $description, NonConformityStatus $status): NonConformity
    {
        return (new NonConformity())
            ->setReference($reference)
            ->setYear($year)
            ->setOrigin(NonConformityOrigin::INTERNAL_AUDIT)
            ->setDescription($description)
            ->setStatus($status);
    }

    public function testSummarisesOnlyTheClosingYear(): void
    {
        $repo = $this->createMock(NonConformityRepository::class);
        $repo->method('findAllOrdered')->willReturn([
            $this->nc(2026, 'NC.AE.2026.01', 'Residuos mal segregados', NonConformityStatus::OPEN),
            $this->nc(2026, 'NC.AI.2026.02', 'Falta de registro', NonConformityStatus::CLOSED),
            $this->nc(2025, 'NC.AE.2025.09', 'De otro curso', NonConformityStatus::OPEN),
        ]);

        $summary = (new NonConformitiesSummaryProvider($repo))->summarize('2025-2026');

        self::assertStringContainsString('año 2026', $summary);
        // Each line now carries the origin for traceability.
        self::assertStringContainsString('NC.AE.2026.01 (Auditoría interna):', $summary);
        self::assertStringContainsString('NC.AI.2026.02', $summary);
        self::assertStringNotContainsString('NC.AE.2025.09', $summary);
    }

    public function testReturnsEmptyWhenNoneInTheYear(): void
    {
        $repo = $this->createMock(NonConformityRepository::class);
        $repo->method('findAllOrdered')->willReturn([
            $this->nc(2024, 'NC.AE.2024.01', 'De un año anterior', NonConformityStatus::CLOSED),
        ]);

        self::assertSame('', (new NonConformitiesSummaryProvider($repo))->summarize('2025-2026'));
    }
}
