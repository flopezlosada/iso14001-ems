<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\ConsumptionReading;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\ConsumptionType;
use App\Enum\PermissionLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional smoke tests for the role-aware dashboard (the post-login landing page). Each role
 * sees a different composition; here we assert the distinguishing section is present, and we
 * exercise the conditional branches (consumption up-to-date vs pending, activity with null fields).
 */
final class HomeControllerTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function persist(User $user, ?Role $role = null): void
    {
        $em = $this->em();
        if (null !== $role) {
            $em->persist($role);
            $user->addAssignedRole($role);
        }
        $em->persist($user);
        $em->flush();
    }

    private function consumptionUser(string $email): User
    {
        $role = (new Role())->setCode('consumos')->setName('Consumos')->setLevel(Area::CONSUMPTION, PermissionLevel::READ);
        $user = (new User())->setFullName('Gestor Consumos')->setEmail($email)->setActive(true);
        $this->persist($user, $role);

        return $user;
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
        $this->persist($user);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.dash-greeting h1');
        self::assertSelectorTextContains('.empty-state', 'gestiones asignadas');
    }

    public function testConsumptionUserWithNoReadingsSeesPending(): void
    {
        $client = static::createClient();
        $client->loginUser($this->consumptionUser('consumo@example.test'));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.quick-links', 'Registrar consumo');
        // No reading for the current month -> the consumption panel nudges with "Pendiente".
        self::assertSelectorTextContains('.dash-grid', 'Pendiente');
    }

    public function testConsumptionUserWithCurrentMonthReadingSeesUpToDate(): void
    {
        $client = static::createClient();
        $client->loginUser($this->consumptionUser('consumo2@example.test'));

        $now = new \DateTimeImmutable();
        $reading = (new ConsumptionReading())
            ->setType(ConsumptionType::WATER)
            ->setPeriodYear((int) $now->format('Y'))
            ->setPeriodMonth((int) $now->format('n'))
            ->setQuantity('120.5');
        $this->em()->persist($reading);
        $this->em()->flush();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.dash-grid', 'Al día');
    }

    public function testAdminSeesPlatformManagementConsumptionAndActivity(): void
    {
        $client = static::createClient();
        $role = (new Role())->setCode('admin')->setName('Administrador')->setAdmin(true);
        $user = (new User())->setFullName('Admin Plataforma')->setEmail('admin@example.test')->setActive(true);
        $this->persist($user, $role);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.quick-links', 'Usuarios');
        // An admin also has consumption access (AreaVoter bypass), so that panel renders too.
        self::assertSelectorTextContains('.dash-grid', 'Consumos');
        self::assertSelectorTextContains('.dash-grid', 'Actividad reciente');
    }

    public function testAdminActivityListRendersEntryWithNullFields(): void
    {
        $client = static::createClient();
        $role = (new Role())->setCode('admin')->setName('Administrador')->setAdmin(true);
        $user = (new User())->setFullName('Admin Log')->setEmail('adminlog@example.test')->setActive(true);
        $this->persist($user, $role);

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
