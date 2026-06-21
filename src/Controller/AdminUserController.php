<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin management of users (the access allow-list). Registering a user here is what lets a
 * person sign in; deactivating one revokes access without deleting the record.
 *
 * The whole /admin area is restricted to ROLE_ADMIN in security.yaml.
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $users->findBy([], ['fullName' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $user = (new User())->setActive(true);

        return $this->handleForm($user, $request, $em, true);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm($user, $request, $em, false);
    }

    private function handleForm(User $user, Request $request, EntityManagerInterface $em, bool $isNew): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($user);
            $em->flush();

            $this->auditLogger->log(
                $isNew ? 'user.created' : 'user.updated',
                'User',
                (string) $user->getId(),
                sprintf('Usuario %s (%s)', $user->getFullName(), $user->getEmail()),
            );
            $this->addFlash('success', 'Usuario guardado.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
