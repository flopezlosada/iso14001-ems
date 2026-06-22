<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ObligationStatus;
use App\Enum\ObligationUrgency;
use App\Repository\AuditLogRepository;
use App\Repository\DocumentRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Post-login landing page: a personal worklist ("lo que me toca"). It answers the question a user
 * actually has on a Monday morning — what of MINE is overdue or due soon — instead of presenting a
 * catalogue of modules. "Mine" means the obligations whose responsible role ({@see User} ↔ Role)
 * the user holds; each item deep-links to the module where it is filled in.
 *
 * Admins additionally get the platform snapshot (recent activity, headline counts). A user with no
 * owned obligations (e.g. a pure admin, or someone whose role owns none) does not see an empty
 * worklist: the admin panel or a friendly all-clear state takes over.
 */
class HomeController extends AbstractController
{
    /**
     * Renders the dashboard: the user's actionable obligations plus, for admins, the platform
     * snapshot.
     *
     * @param DocumentRepository $documents repository providing the obligation register
     * @param AuditLogRepository $auditLogs repository used to list recent activity (admins)
     * @param UserRepository     $users     repository used to count users with access (admins)
     * @param RoleRepository     $roles     repository used to count defined roles (admins)
     *
     * @return Response the rendered dashboard
     */
    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    public function index(
        DocumentRepository $documents,
        AuditLogRepository $auditLogs,
        UserRepository $users,
        RoleRepository $roles,
    ): Response {
        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $user = $this->getUser();

        $worklist = $this->buildWorklist($documents, $user, $today);

        // Platform snapshot for admins: recent activity and headline counts.
        $admin = null;
        if ($isAdmin) {
            $admin = [
                'recent' => $auditLogs->findLatest(6),
                // "Usuarios con acceso" = active users only; inactive ones cannot log in.
                'userCount' => $users->count(['active' => true]),
                'roleCount' => $roles->count([]),
            ];
        }

        $fullName = $user instanceof User ? $user->getFullName() : ($user?->getUserIdentifier() ?? '');

        return $this->render('dashboard/index.html.twig', [
            'greeting' => $this->greetingFor((int) $now->format('G')),
            'firstName' => explode(' ', trim($fullName))[0],
            'today' => $today,
            'admin' => $admin,
            'counts' => $worklist['counts'],
            'actionable' => $worklist['actionable'],
            'hasObligations' => $worklist['total'] > 0,
            // Nothing of one's own AND no admin tools: a genuinely empty landing.
            'hasNothing' => 0 === $worklist['total'] && !$isAdmin,
        ]);
    }

    /**
     * Builds the user's worklist from the obligation register: the date-driven obligations whose
     * responsible role the user holds, counted by urgency and with the overdue/due-soon ones
     * surfaced as an actionable list (most overdue first).
     *
     * Event-driven obligations (no fixed cadence) are excluded: they are reactive, not "due".
     * Not-applicable ones are skipped entirely.
     *
     * @param DocumentRepository $documents the obligation register
     * @param object|null        $user      the authenticated user, if any
     * @param \DateTimeImmutable $today     the reference date for urgency
     *
     * @return array{counts: array{overdue: int, due_soon: int, on_track: int}, actionable: list<array{title: string, code: ?string, urgency: ObligationUrgency, dueDate: ?\DateTimeImmutable, daysUntil: ?int, route: ?string}>, total: int}
     */
    private function buildWorklist(DocumentRepository $documents, ?object $user, \DateTimeImmutable $today): array
    {
        $counts = ['overdue' => 0, 'due_soon' => 0, 'on_track' => 0];
        $actionable = [];

        // No roles → nothing is the user's responsibility; skip the query and the loop entirely.
        if (!$user instanceof User || $user->getAssignedRoles()->isEmpty()) {
            return ['counts' => $counts, 'actionable' => $actionable, 'total' => 0];
        }

        foreach ($documents->findObligations() as $obligation) {
            // The home is the pending worklist: obligations already done or marked not-applicable
            // are off the user's plate (the "Qué toca" cockpit still lists them). Skip both here.
            $status = $obligation->getStatus();
            if (ObligationStatus::DONE === $status || ObligationStatus::NOT_APPLICABLE === $status) {
                continue;
            }
            $responsible = $obligation->getResponsibleRole();
            if (null === $responsible || !$user->holdsRole($responsible)) {
                continue; // not the user's responsibility
            }

            $urgency = $obligation->dueStatus($today);
            if (ObligationUrgency::EVENT_DRIVEN === $urgency) {
                continue; // reactive, never "due"
            }
            ++$counts[$urgency->value];

            if (ObligationUrgency::OVERDUE === $urgency || ObligationUrgency::DUE_SOON === $urgency) {
                $dueDate = $obligation->nextReviewDate();
                $actionable[] = [
                    'title' => $obligation->getTitle(),
                    'code' => $obligation->getCode(),
                    'urgency' => $urgency,
                    'dueDate' => $dueDate,
                    'daysUntil' => null !== $dueDate ? (int) $today->diff($dueDate)->format('%r%a') : null,
                    'route' => $obligation->getLinkedArea()?->indexRoute(),
                ];
            }
        }

        // Most overdue first, then the soonest upcoming: ascending due date does exactly that.
        usort($actionable, static fn (array $a, array $b): int => $a['dueDate'] <=> $b['dueDate']);

        return ['counts' => $counts, 'actionable' => $actionable, 'total' => array_sum($counts)];
    }

    /**
     * Returns a time-of-day greeting in Spanish for the given 24h hour.
     *
     * @param int $hour the hour of day (0-23)
     *
     * @return string the greeting ("Buenos días", "Buenas tardes" or "Buenas noches")
     */
    private function greetingFor(int $hour): string
    {
        return match (true) {
            $hour >= 6 && $hour < 13 => 'Buenos días',
            $hour >= 13 && $hour < 21 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }
}
