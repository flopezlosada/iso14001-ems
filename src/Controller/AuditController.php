<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only activity trail. How it is surfaced per area will be refined later; this is the
 * baseline chronological view.
 */
class AuditController extends AbstractController
{
    #[Route('/audit', name: 'audit_index', methods: ['GET'])]
    public function index(AuditLogRepository $auditLogs): Response
    {
        return $this->render('audit/index.html.twig', [
            'entries' => $auditLogs->findLatest(100),
        ]);
    }
}
