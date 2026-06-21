<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\Area;
use App\Repository\AuditLogRepository;
use App\Repository\ConsumptionReadingRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\Voter\AreaVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Post-login landing page: a role-aware dashboard ("lo que importa hoy"). What each user sees is
 * driven by the existing authorization model, not by hard-coded role names: admins get platform
 * management and recent activity; users with consumption access get their consumption status.
 *
 * Sections are deliberately limited to modules that actually exist and hold data (consumption,
 * activity trail, user/role administration). As new areas land (documents, alerts, waste), each
 * gets its own section here, gated by the same per-area permissions.
 */
class HomeController extends AbstractController
{
    /**
     * Renders the dashboard for the authenticated user, assembling only the sections the user is
     * allowed to see and that have real data to show.
     *
     * @param ConsumptionReadingRepository $consumptionReadings repository used to summarise the year's readings
     * @param AuditLogRepository           $auditLogs           repository used to list recent activity (admins)
     * @param UserRepository               $users               repository used to count users with access (admins)
     * @param RoleRepository               $roles               repository used to count defined roles (admins)
     *
     * @return Response the rendered dashboard
     */
    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    public function index(
        ConsumptionReadingRepository $consumptionReadings,
        AuditLogRepository $auditLogs,
        UserRepository $users,
        RoleRepository $roles,
    ): Response {
        $now = new \DateTimeImmutable();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $hour = (int) $now->format('G');

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $canConsumption = $this->isGranted(AreaVoter::READ, Area::CONSUMPTION);

        // Consumption snapshot: only what the data supports (readings this year, current month
        // coverage). We do NOT compute "pending" against an expected cadence because that schedule
        // is not modelled yet (it will come with the alerts engine).
        $consumption = null;
        if ($canConsumption) {
            $readings = $consumptionReadings->findForYear($year);
            $monthsCovered = [];
            $currentMonthCount = 0;
            foreach ($readings as $reading) {
                $monthsCovered[$reading->getPeriodMonth()] = true;
                if ($reading->getPeriodMonth() === $month) {
                    ++$currentMonthCount;
                }
            }
            $consumption = [
                'year' => $year,
                'total' => \count($readings),
                'monthsCovered' => \count($monthsCovered),
                'currentMonthCount' => $currentMonthCount,
            ];
        }

        // Platform snapshot for admins: recent activity and headline counts (shown discreetly, not
        // as decorative metric cards).
        $admin = null;
        if ($isAdmin) {
            $admin = [
                'recent' => $auditLogs->findLatest(6),
                // "Usuarios con acceso" = active users only; inactive ones cannot log in.
                'userCount' => $users->count(['active' => true]),
                'roleCount' => $roles->count([]),
            ];
        }

        $user = $this->getUser();
        $fullName = $user instanceof User ? $user->getFullName() : ($user?->getUserIdentifier() ?? '');

        return $this->render('dashboard/index.html.twig', [
            'greeting' => $this->greetingFor($hour),
            'firstName' => explode(' ', trim($fullName))[0],
            'today' => $now,
            'isAdmin' => $isAdmin,
            'canConsumption' => $canConsumption,
            'consumption' => $consumption,
            'admin' => $admin,
            'hasNothing' => !$isAdmin && !$canConsumption,
        ]);
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
