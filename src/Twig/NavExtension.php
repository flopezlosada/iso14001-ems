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
 * Labels, routes and the display order all come from {@see Area} itself (single source of truth),
 * so this extension only owns the permission filtering, never the wording, links or ordering.
 */
class NavExtension extends AbstractExtension
{
    public function __construct(private readonly AuthorizationCheckerInterface $authChecker)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_pillars', $this->navPillars(...)),
            new TwigFunction('area_pillars', $this->areaPillars(...)),
        ];
    }

    /**
     * Every functional area grouped by its PDCA phase, in the same menu order as {@see navPillars()}
     * but WITHOUT any permission filter. For admin screens that must show the whole catalog (the
     * role permission editor), where hiding areas the editor cannot personally read would be wrong.
     *
     * @return list<array{phase: PdcaPhase, areas: list<Area>}> phases in cycle order, each with its areas
     */
    public function areaPillars(): array
    {
        return Area::groupedByPhase();
    }

    /**
     * The menu as a list of phases, each with the readable areas it owns, in display order. Phases
     * with no visible area are omitted.
     *
     * @return list<array{label: string, items: list<array{label: string, route: string, prefix: string}>}>
     */
    public function navPillars(): array
    {
        $pillars = [];
        foreach (Area::groupedByPhase() as $group) {
            $items = [];
            foreach ($group['areas'] as $area) {
                if (!$this->authChecker->isGranted(AreaVoter::READ, $area)) {
                    continue;
                }
                $route = $area->indexRoute();
                $items[] = [
                    'label' => $area->label(),
                    'route' => $route,
                    // Route-name prefix for the "is-active" highlight: 'consumption_index' → 'consumption',
                    // which all of that module's routes share (consumption_new, consumption_year, …).
                    'prefix' => str_replace('_index', '', $route),
                ];
            }
            if ([] !== $items) {
                $pillars[] = ['label' => $group['phase']->label(), 'items' => $items];
            }
        }

        return $pillars;
    }
}
