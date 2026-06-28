<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Enum\Area;
use App\Security\Voter\AreaVoter;
use App\Twig\NavExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Unit tests for {@see NavExtension}: the sidebar menu must group areas by PDCA phase, show only
 * the areas the user can read, and drop empty phases. These tests pin that contract so the menu
 * cannot silently leak modules a role should not see, nor hide one it should.
 */
final class NavExtensionTest extends TestCase
{
    /**
     * Builds a NavExtension whose authorization checker grants AREA_READ only for the given areas
     * (mimicking a role's permission matrix); every other area is denied.
     *
     * @param list<Area> $readable the areas the simulated user may read (all areas if null)
     */
    private function extensionGranting(?array $readable): NavExtension
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (string $attribute, mixed $subject): bool => AreaVoter::READ === $attribute
                && (null === $readable || \in_array($subject, $readable, true)),
        );

        return new NavExtension($checker);
    }

    public function testAdminSeesEveryPhaseAndArea(): void
    {
        // null = everything granted (an admin bypasses the matrix).
        $pillars = $this->extensionGranting(null)->navPillars();

        self::assertSame(
            ['Planificar', 'Implementación', 'Verificar', 'Mejora'],
            array_column($pillars, 'label'),
            'The four PDCA phases must appear in cycle order.',
        );

        // Every area is reachable: no module silently missing from the menu.
        $routes = [];
        foreach ($pillars as $pillar) {
            foreach ($pillar['items'] as $item) {
                $routes[] = $item['route'];
            }
        }
        self::assertCount(\count(Area::cases()), $routes);
        foreach (Area::cases() as $area) {
            self::assertContains($area->indexRoute(), $routes);
        }
    }

    public function testPlanPhaseLeadsWithContextAreas(): void
    {
        $pillars = $this->extensionGranting(null)->navPillars();
        $plan = $pillars[0];

        self::assertSame('Planificar', $plan['label']);
        self::assertSame(
            ['Partes interesadas', 'Análisis DAFO'],
            array_slice(array_column($plan['items'], 'label'), 0, 2),
            'Context areas (interested parties, DAFO) should open the Plan folder.',
        );
    }

    public function testOnlyReadableAreasAppearAndEmptyPhasesAreDropped(): void
    {
        // Secretaría-like: consumos + residuos only. Both live in the Do phase.
        $pillars = $this->extensionGranting([Area::CONSUMPTION, Area::WASTE])->navPillars();

        self::assertCount(1, $pillars);
        self::assertSame('Implementación', $pillars[0]['label']);
        self::assertSame(
            ['Consumos', 'Residuos'],
            array_column($pillars[0]['items'], 'label'),
        );
    }

    public function testUserWithoutAnyReadableAreaGetsNoPillars(): void
    {
        self::assertSame([], $this->extensionGranting([])->navPillars());
    }

    public function testSinglePillarOutsideDoIsBuiltAndOthersDropped(): void
    {
        // No conformidades is the only area in the Act phase: a role with just that read access
        // must still get exactly one pillar (proves the empty-pillar drop works for any phase).
        $pillars = $this->extensionGranting([Area::NONCONFORMITY])->navPillars();

        self::assertCount(1, $pillars);
        self::assertSame('Mejora', $pillars[0]['label']);
        self::assertSame(['No conformidades'], array_column($pillars[0]['items'], 'label'));
    }

    public function testItemPrefixDrivesTheActiveHighlight(): void
    {
        $pillars = $this->extensionGranting([Area::EMERGENCY, Area::NONCONFORMITY])->navPillars();

        $byLabel = [];
        foreach ($pillars as $pillar) {
            foreach ($pillar['items'] as $item) {
                $byLabel[$item['label']] = $item['prefix'];
            }
        }

        // The prefix is the route name without '_index'; every route of the module shares it.
        self::assertSame('emergency_drill', $byLabel['Simulacros']);
        self::assertSame('non_conformity', $byLabel['No conformidades']);
    }

    public function testRiskAreaRoutesActivateTheRiskOpportunityItem(): void
    {
        // Riesgos uses 'risk_index' → prefix 'risk'. The process-areas catalogue (risk_area_*) is
        // part of that module, so its pages intentionally light up the same menu item. Pinned here
        // so a future module must not introduce a colliding 'risk*' prefix by accident.
        $pillars = $this->extensionGranting([Area::RISK_OPPORTUNITY])->navPillars();
        $prefix = $pillars[0]['items'][0]['prefix'];

        self::assertSame('risk', $prefix);
        self::assertStringStartsWith($prefix, 'risk_area_index');
    }
}
