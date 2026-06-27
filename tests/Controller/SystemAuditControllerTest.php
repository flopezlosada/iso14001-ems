<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\AuditType;
use App\Enum\PermissionLevel;
use App\Repository\SystemAuditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test of the system-audit flow: registering an audit persists it, and its detail page
 * renders. DAMA rolls back writes between tests.
 */
final class SystemAuditControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('auditor')->setName('Auditorías')->setLevel(Area::SYSTEM_AUDIT, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('system-audit-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    private function audits(): SystemAuditRepository
    {
        return static::getContainer()->get(SystemAuditRepository::class);
    }

    public function testRegisteringAnAuditPersistsItAndShowsItsDetail(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/system-audits/new');
        $client->submitForm('Guardar', [
            'system_audit[year]' => '2026',
            'system_audit[type]' => AuditType::INTERNAL->value,
            'system_audit[conductedOn]' => '2026-01-22',
            'system_audit[auditor]' => 'Auditora interna',
            'system_audit[objective]' => 'Verificar la implantación del sistema.',
        ]);

        self::assertResponseRedirects();

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $all = $this->audits()->findAllOrdered();
        self::assertCount(1, $all);
        self::assertSame(2026, $all[0]->getYear());
        self::assertSame(AuditType::INTERNAL, $all[0]->getType());
        self::assertSame('Auditora interna', $all[0]->getAuditor());

        // The detail page renders.
        $client->request('GET', '/system-audits/'.$all[0]->getId());
        self::assertResponseIsSuccessful();
    }

    public function testListingRequiresReadPermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // A user with no permission on the area must not see the register.
        $user = new User();
        $user->setFullName('Sin permisos')->setEmail('system-audit-noperm@example.test')->setActive(true);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/system-audits');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegisteringRequiresWritePermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // READ is enough to view but not to register.
        $role = new Role();
        $role->setCode('auditor-ro')->setName('Auditorías (lectura)')->setLevel(Area::SYSTEM_AUDIT, PermissionLevel::READ);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Solo lectura')->setEmail('system-audit-ro@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/system-audits/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeletingRemovesTheAudit(): void
    {
        $client = $this->loggedInClient();
        $this->createAudit($client);

        // The delete button lives on the list, not on the detail page.
        $client->request('GET', '/system-audits');
        $client->submitForm('Eliminar');

        self::assertResponseRedirects('/system-audits');
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertCount(0, $this->audits()->findAllOrdered());
    }

    public function testDeletingWithAnInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->loggedInClient();
        $id = $this->createAudit($client);

        $client->request('POST', '/system-audits/'.$id.'/delete', ['_token' => 'wrong']);
        self::assertResponseStatusCodeSame(403);

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertCount(1, $this->audits()->findAllOrdered());
    }

    public function testReportReturns404WhenThereIsNoReport(): void
    {
        $client = $this->loggedInClient();
        $id = $this->createAudit($client);

        $client->request('GET', '/system-audits/'.$id.'/report');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Registers a bare audit (no report file) and returns its id.
     */
    private function createAudit(KernelBrowser $client): int
    {
        $client->request('GET', '/system-audits/new');
        $client->submitForm('Guardar', [
            'system_audit[year]' => '2026',
            'system_audit[type]' => AuditType::INTERNAL->value,
            'system_audit[auditor]' => 'Auditora interna',
        ]);

        static::getContainer()->get(EntityManagerInterface::class)->clear();

        return $this->audits()->findAllOrdered()[0]->getId();
    }
}
