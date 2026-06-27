<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\InterestedParty;
use App\Repository\InterestedPartyRepository;
use App\Service\ManagementReview\Provider\InterestedPartiesSummaryProvider;
use PHPUnit\Framework\TestCase;

final class InterestedPartiesSummaryProviderTest extends TestCase
{
    /**
     * Builds an in-memory interested party; null incidents mirror real rows (the F.04.0 sheet often
     * leaves incidents blank or writes "NO").
     */
    private function party(string $name, string $needs, ?string $incidents): InterestedParty
    {
        return (new InterestedParty())
            ->setReviewYear(2025)
            ->setName($name)
            ->setNeedsAndExpectations($needs)
            ->setIncidents($incidents);
    }

    public function testSummarisesThePartiesOfTheCoursesOpeningYear(): void
    {
        $repo = $this->createMock(InterestedPartyRepository::class);
        // The course "2025-2026" maps to the opening year 2025 (ExerciseYears::startYear).
        $repo->expects(self::once())->method('findForYear')->with(2025)->willReturn([
            $this->party('Proveedores', 'Puntualidad en los pagos', null),
            $this->party('Alumnos', 'Atención personalizada', 'NO'),
            $this->party('Gestores de residuos', 'Segregación correcta', 'Retirada con retraso en mayo'),
        ]);

        $summary = (new InterestedPartiesSummaryProvider($repo))->summarize('2025-2026');

        self::assertStringContainsString('identificadas en 2025: 3', $summary);
        // A null or "NO" incidents value must not produce an "(incidencias: …)" note: the line is bare.
        self::assertStringContainsString('- Proveedores: Puntualidad en los pagos'."\n", $summary."\n");
        self::assertStringNotContainsString('incidencias: NO', $summary);
        // A real incident is reported.
        self::assertStringContainsString('(incidencias: Retirada con retraso en mayo)', $summary);
    }

    public function testReturnsEmptyWhenNoPartiesForTheYear(): void
    {
        $repo = $this->createMock(InterestedPartyRepository::class);
        $repo->method('findForYear')->with(2025)->willReturn([]);

        self::assertSame('', (new InterestedPartiesSummaryProvider($repo))->summarize('2025-2026'));
    }
}
