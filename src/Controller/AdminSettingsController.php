<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SettingsType;
use App\Service\AuditLogger;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Business configuration screen (significance thresholds and auto-intensity bounds). The whole
 * /admin area is restricted to ROLE_ADMIN in security.yaml; the attribute documents it locally too.
 */
#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class AdminSettingsController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Shows and saves the single settings row. The provider returns the saved row, or a transient
     * one with defaults that is persisted on first save.
     */
    #[Route('', name: 'admin_settings_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SettingsProvider $settings, EntityManagerInterface $em): Response
    {
        $config = $settings->get();
        $form = $this->createForm(SettingsType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($config);
            $em->flush();
            $settings->invalidate();

            $this->auditLogger->log('settings.updated', 'Settings', (string) $config->getId(), 'Ajustes de cálculo actualizados');
            $this->addFlash('success', 'Ajustes guardados.');

            return $this->redirectToRoute('admin_settings_edit');
        }

        return $this->render('admin/settings/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
