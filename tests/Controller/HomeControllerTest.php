<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Document;
use App\Entity\Role;
use App\Entity\ScheduledAlert;
use App\Entity\User;
use App\Enum\AlertFrequency;
use App\Enum\Area;
use App\Enum\DocumentType;
use App\Enum\IsoChapter;
use App\Enum\ObligationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the personal worklist dashboard ("lo que me toca"): a user sees the
 * obligations whose responsible role they hold, scoped and surfaced by urgency; admins also get
 * the platform panel. We exercise the scoping (mine vs not-mine), the overdue copy and the
 * admin/empty branches.
 */
final class HomeControllerTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function persistUser(User $user, ?Role $role = null): void
    {
        $em = $this->em();
        if (null !== $role) {
            $em->persist($role);
            $user->addAssignedRole($role);
        }
        $em->persist($user);
        $em->flush();
    }

    /**
     * Persists an obligation (a chapter-bound document, so it is returned by findObligations) due on
     * the given date and owned by the given role.
     */
    private function obligation(Role $role, string $title, ?Area $area, \DateTimeImmutable $due, ObligationStatus $status = ObligationStatus::PENDING): void
    {
        $alert = (new ScheduledAlert())->setFrequency(AlertFrequency::ANNUAL)->setNextDueDate($due);
        $document = (new Document())
            ->setTitle($title)
            ->setType(DocumentType::RECORD)
            ->setIsoChapter(IsoChapter::PLANNING)
            ->setStatus($status)
            ->setLinkedArea($area)
            ->setResponsibleRole($role)
            ->addAlert($alert);

        $em = $this->em();
        $em->persist($document);
        $em->persist($alert);
        $em->flush();
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testUserWithoutRolesSeesEmptyState(): void
    {
        $client = static::createClient();
        $user = (new User())->setFullName('Sin Rol')->setEmail('norole@example.test')->setActive(true);
        $this->persistUser($user);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.dash-greeting h1');
        self::assertSelectorTextContains('.empty-state', 'gestiones asignadas');
    }

    public function testUserSeesOwnOverdueObligationInWorklist(): void
    {
        $client = static::createClient();
        $role = (new Role())->setCode('mant')->setName('Mantenimiento');
        $user = (new User())->setFullName('Pedro Mantenimiento')->setEmail('pedro@example.test')->setActive(true);
        $this->persistUser($user, $role);
        $this->obligation($role, 'Revisión de extintores', Area::CONSUMPTION, (new \DateTimeImmutable('today'))->modify('-10 days'));
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.worklist-stats');
        self::assertSelectorTextContains('.worklist', 'Revisión de extintores');
        self::assertSelectorTextContains('.worklist', 'venció hace');
    }

    public function testObligationOfAnotherRoleIsNotInMyWorklist(): void
    {
        $client = static::createClient();
        $mine = (new Role())->setCode('mant')->setName('Mantenimiento');
        $other = (new Role())->setCode('sec')->setName('Secretaría');
        $user = (new User())->setFullName('Pedro Mantenimiento')->setEmail('pedro2@example.test')->setActive(true);
        $this->persistUser($user, $mine);
        $this->em()->persist($other);
        $this->em()->flush();
        $this->obligation($other, 'Plan de formación', Area::TRAINING, (new \DateTimeImmutable('today'))->modify('-3 days'));
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        // The obligation belongs to another role, so the worklist is empty: the user lands on the
        // empty state, not on someone else's task.
        self::assertSelectorNotExists('.worklist-stats');
        self::assertSelectorTextContains('.empty-state', 'gestiones asignadas');
    }

    public function testOwnObligationsAllOnTrackShowsClearState(): void
    {
        $client = static::createClient();
        $role = (new Role())->setCode('mant')->setName('Mantenimiento');
        $user = (new User())->setFullName('Pedro Mantenimiento')->setEmail('pedro3@example.test')->setActive(true);
        $this->persistUser($user, $role);
        // Due in 60 days: beyond the 30-day "soon" window, so on track (nothing actionable).
        $this->obligation($role, 'Auditoría interna', Area::CONSUMPTION, (new \DateTimeImmutable('today'))->modify('+60 days'));
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.worklist-stats');
        self::assertSelectorExists('.worklist-clear');
        // No actionable list when nothing is overdue or due soon.
        self::assertSelectorNotExists('.worklist .worklist-item');
    }

    public function testDoneObligationIsNotInWorklist(): void
    {
        $client = static::createClient();
        $role = (new Role())->setCode('mant')->setName('Mantenimiento');
        $user = (new User())->setFullName('Pedro Mantenimiento')->setEmail('pedro4@example.test')->setActive(true);
        $this->persistUser($user, $role);
        // Marked done, even though its date has passed: the home leaves it off the plate.
        $this->obligation($role, 'Revisión de extintores', Area::CONSUMPTION, (new \DateTimeImmutable('today'))->modify('-10 days'), ObligationStatus::DONE);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.worklist-stats');
        self::assertSelectorTextContains('.empty-state', 'gestiones asignadas');
    }

    public function testAdminSeesPlatformPanel(): void
    {
        $client = static::createClient();
        $role = (new Role())->setCode('admin')->setName('Administrador')->setAdmin(true);
        $user = (new User())->setFullName('Admin Plataforma')->setEmail('admin@example.test')->setActive(true);
        $this->persistUser($user, $role);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.dash-admin', 'La plataforma');
        self::assertSelectorTextContains('.dash-admin', 'Usuarios');
    }

    public function testAdminActivityListRendersEntryWithNullFields(): void
    {
        $client = static::createClient();
        $role = (new Role())->setCode('admin')->setName('Administrador')->setAdmin(true);
        $user = (new User())->setFullName('Admin Log')->setEmail('adminlog@example.test')->setActive(true);
        $this->persistUser($user, $role);

        // A real entry can have null actor and null summary; the view must fall back gracefully.
        $this->em()->persist(new AuditLog('user.login'));
        $this->em()->flush();

        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.activity', 'sistema');
        self::assertSelectorTextContains('.activity', 'user.login');
    }
}
