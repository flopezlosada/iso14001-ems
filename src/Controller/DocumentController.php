<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Enum\Area;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use App\Enum\ObligationUrgency;
use App\Enum\PdcaPhase;
use App\Repository\DocumentRepository;
use App\Repository\EnvironmentalAspectRepository;
use App\Service\AspectIntensityEstimator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The obligations cockpit: the two views that turn the document register into a working tool.
 *
 * "Qué toca" (the day-to-day) groups the obligations by date-derived urgency, regardless of PDCA
 * phase, because what is due is scattered across all four phases. "Estructura SGA" presents the
 * same obligations under the centre's own PDCA folders, for navigating, archiving and showing the
 * auditor. The modules are NOT navigated to from a menu: each obligation deep-links to the module
 * where it is filled in (or flags that its module is pending).
 */
final class DocumentController extends AbstractController
{
    /**
     * Maps a linked module ({@see Area}) to its module index route, so an obligation can deep-link
     * to where it is actually filled in. Areas absent here have no destination yet.
     *
     * @var array<string, string>
     */
    private const array MODULE_ROUTES = [
        Area::CONSUMPTION->value => 'consumption_index',
        Area::WASTE->value => 'waste_index',
        Area::NONCONFORMITY->value => 'non_conformity_index',
        Area::SUPPLIER->value => 'supplier_index',
        Area::TRAINING->value => 'training_index',
        Area::LEGAL_REQUIREMENT->value => 'legal_requirement_index',
        Area::EMERGENCY->value => 'emergency_drill_index',
        Area::ASPECT->value => 'aspect_index',
        Area::RISK_OPPORTUNITY->value => 'risk_index',
        Area::OBJECTIVE->value => 'objective_index',
        Area::INDICATOR->value => 'indicator_index',
    ];

    /**
     * "Qué toca": the obligations grouped by urgency (overdue / due soon / on track / event-driven),
     * plus the ones marked not-applicable set aside. This is the day-to-day landing view.
     */
    #[Route('/obligaciones', name: 'obligation_index', methods: ['GET'])]
    public function index(
        DocumentRepository $documents,
        EnvironmentalAspectRepository $aspects,
        AspectIntensityEstimator $intensityEstimator,
    ): Response {
        $today = new \DateTimeImmutable('today');

        // Pre-seed the buckets so the template always renders them in a fixed, meaningful order.
        $groups = [
            ObligationUrgency::OVERDUE->value => [],
            ObligationUrgency::DUE_SOON->value => [],
            ObligationUrgency::ON_TRACK->value => [],
            ObligationUrgency::EVENT_DRIVEN->value => [],
        ];
        $notApplicable = [];

        foreach ($documents->findObligations() as $obligation) {
            if (ObligationStatus::NOT_APPLICABLE === $obligation->getStatus()) {
                $notApplicable[] = $obligation;

                continue;
            }
            $groups[$obligation->dueStatus($today)->value][] = $obligation;
        }

        return $this->render('document/index.html.twig', [
            'today' => $today,
            'groups' => $groups,
            'notApplicable' => $notApplicable,
            'moduleRoutes' => self::MODULE_ROUTES,
            // Consumption aspects already trending worse than the threshold: surfaced proactively so
            // a likely-significant aspect is seen now, not only at the yearly evaluation.
            'aspectsToWatch' => $intensityEstimator->watchList($aspects->findLinkedToConsumption(), $today),
        ]);
    }

    /**
     * "Estructura SGA": the obligations under the centre's PDCA folders (phase → ISO chapter →
     * obligations), for navigation, completeness overview and showing the auditor.
     */
    #[Route('/sga', name: 'obligation_structure', methods: ['GET'])]
    public function structure(DocumentRepository $documents): Response
    {
        return $this->render('document/structure.html.twig', [
            'structure' => $this->groupByPhase($documents->findObligations()),
            'moduleRoutes' => self::MODULE_ROUTES,
        ]);
    }

    /**
     * Groups obligations into the PDCA → ISO chapter tree, preserving the natural enum order
     * (phases 00→03, chapters 4→10) and dropping empty branches.
     *
     * @param Document[] $obligations the obligations to arrange
     *
     * @return array<int, array{phase: PdcaPhase, chapters: array<int, array{chapter: IsoChapter, items: Document[]}>}>
     */
    private function groupByPhase(array $obligations): array
    {
        $tree = [];
        foreach ($obligations as $obligation) {
            $chapter = $obligation->getIsoChapter();
            if (null === $chapter) {
                continue;
            }
            $tree[$chapter->phase()->value][$chapter->value][] = $obligation;
        }

        $result = [];
        foreach (PdcaPhase::cases() as $phase) {
            $chapters = [];
            foreach (IsoChapter::cases() as $chapter) {
                $items = $tree[$phase->value][$chapter->value] ?? [];
                if ([] !== $items) {
                    $chapters[] = ['chapter' => $chapter, 'items' => $items];
                }
            }
            if ([] !== $chapters) {
                $result[] = ['phase' => $phase, 'chapters' => $chapters];
            }
        }

        return $result;
    }
}
