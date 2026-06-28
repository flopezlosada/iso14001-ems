<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Role;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Form\RoleType;
use App\Repository\RoleRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin management of roles and their per-area permission matrix. Admins only.
 */
#[Route('/admin/roles')]
#[IsGranted('ROLE_ADMIN')]
class AdminRoleController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    #[Route('', name: 'admin_role_index', methods: ['GET'])]
    public function index(RoleRepository $roles): Response
    {
        return $this->render('admin/role/index.html.twig', [
            'roles' => $roles->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm(new Role(), $request, $em, true);
    }

    #[Route('/{id}/edit', name: 'admin_role_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Role $role, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm($role, $request, $em, false);
    }

    private function handleForm(Role $role, Request $request, EntityManagerInterface $em, bool $isNew): Response
    {
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach (Area::cases() as $area) {
                $level = $form->get(RoleType::PERMISSION_PREFIX.$area->value)->getData();
                $role->setLevel($area, $level instanceof PermissionLevel ? $level : PermissionLevel::NONE);
            }

            $em->persist($role);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'role.created' : 'role.updated',
                'Role',
                (string) $role->getId(),
                sprintf('Rol %s', $role->getName()),
            );
            $this->addFlash('success', 'Rol guardado.');

            return $this->redirectToRoute('admin_role_index');
        }

        return $this->render('admin/role/form.html.twig', [
            'form' => $form,
            'role' => $role,
        ]);
    }
}
