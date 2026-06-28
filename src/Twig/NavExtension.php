<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\Area;
use App\Enum\PdcaPhase;
use App\Security\Voter\AreaVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Builds the sidebar's module navigation: the functional {@see Area}s grouped by {@see PdcaPhase}
 * (the centre's PLAN/DO/CHECK/ACT folders) and filtered to those the current user may read.
 *
 * Two design choices, both so the menu adapts to the person instead of listing everything:
 *  - a user only sees an area for which their roles grant at least {@see AreaVoter::READ} (admins,
 *    who bypass the matrix, see all);
 *  - a phase with no visible area is dropped entirely, so e.g. Secretaría does not see empty folders.
 *
 * Labels and routes come from {@see Area} itself (single source of truth), so this extension only
 * owns the *ordering* of the menu, never the wording or the links.
 */
class NavExtension extends AbstractExtension
{
    /**
     * The curated order in which areas appear within their phase (context first in Plan, the
     * operational records first in Do, etc.). Areas missing here still render — at the end of their
     * phase — so a newly added module is never silently absent from the menu.
     *
     * @var list<Area>
     */
    private const ORDER = [
        // Plan
        Area::INTERESTED_PARTY, Area::DAFO, Area::ASPECT, Area::RISK_OPPORTUNITY, Area::OBJECTIVE, Area::LEGAL_REQUIREMENT,
        // Do
        Area::CONSUMPTION, Area::WASTE, Area::OPERATIONAL_CONTROL, Area::EMERGENCY, Area::TRAINING, Area::COMMUNICATION, Area::SUPPLIER,
        // Check
        Area::INDICATOR, Area::SYSTEM_AUDIT, Area::MANAGEMENT_REVIEW,
        // Act
        Area::NONCONFORMITY,
    ];

    public function __construct(private readonly AuthorizationCheckerInterface $authChecker)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_pillars', $this->navPillars(...)),
        ];
    }

    /**
     * The menu as a list of phases, each with the readable areas it owns, in display order. Phases
     * with no visible area are omitted.
     *
     * @return list<array{label: string, items: list<array{label: string, route: string, prefix: string}>}>
     */
    public function navPillars(): array
    {
        $byPhase = [];
        foreach ($this->orderedAreas() as $area) {
            if (!$this->authChecker->isGranted(AreaVoter::READ, $area)) {
                continue;
            }
            $route = $area->indexRoute();
            $byPhase[$area->phase()->value][] = [
                'label' => $area->label(),
                'route' => $route,
                // Route-name prefix for the "is-active" highlight: 'consumption_index' → 'consumption',
                // which all of that module's routes share (consumption_new, consumption_year, …).
                'prefix' => str_replace('_index', '', $route),
            ];
        }

        $pillars = [];
        foreach (PdcaPhase::cases() as $phase) {
            $items = $byPhase[$phase->value] ?? [];
            if ([] !== $items) {
                $pillars[] = ['label' => $phase->label(), 'items' => $items];
            }
        }

        return $pillars;
    }

    /**
     * {@see self::ORDER} followed by any area not listed there (defensive against drift), so every
     * existing area is guaranteed a slot.
     *
     * @return list<Area>
     */
    private function orderedAreas(): array
    {
        $ordered = self::ORDER;
        foreach (Area::cases() as $area) {
            if (!\in_array($area, $ordered, true)) {
                $ordered[] = $area;
            }
        }

        return $ordered;
    }
}
