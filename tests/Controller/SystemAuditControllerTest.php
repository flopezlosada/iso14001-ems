<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\NonConformity;
use App\Entity\Role;
use App\Entity\SystemAudit;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\AuditType;
use App\Enum\NonConformityOrigin;
use App\Enum\PermissionLevel;
use App\Repository\CorrectiveActionRepository;
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

    public function testInternalAuditReminderShowsUntilThisYearsInternalAuditExists(): void
    {
        $client = $this->loggedInClient();
        $year = (int) date('Y');

        // No internal audit for the current year yet -> the reminder is shown.
        $client->request('GET', '/system-audits');
        self::assertStringContainsString('Auditoría interna de '.$year.' pendiente', (string) $client->getResponse()->getContent());

        // Once this year's internal audit exists, the reminder is gone.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist((new SystemAudit())->setYear($year)->setType(AuditType::INTERNAL)->setAuditor('Auditora interna'));
        $em->flush();

        $client->request('GET', '/system-audits');
        self::assertStringNotContainsString('Auditoría interna de '.$year.' pendiente', (string) $client->getResponse()->getContent());
    }

    public function testGenerateActionPlanSeedsADraftActionPerFindingAndIsIdempotent(): void
    {
        $client = $this->clientWithAuditAndNonConformityWrite();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $audit = (new SystemAudit())->setYear(2026)->setType(AuditType::EXTERNAL)->setAuditor('Entidad certificadora');
        $em->persist($audit);
        $finding = (new NonConformity())
            ->setOrigin(NonConformityOrigin::EXTERNAL_AUDIT)
            ->setYear(2026)
            ->setSequence(1)
            ->setReference('NC.AE.2026.01')
            ->setDescription('Incumplimiento detectado en el informe externo.')
            ->setAudit($audit);
        $em->persist($finding);
        $em->flush();
        $auditId = $audit->getId();

        // Generate the draft plan from the audit detail page (the form carries the CSRF token).
        $client->request('GET', '/system-audits/'.$auditId);
        $client->submitForm('Generar plan de acciones');
        self::assertResponseRedirects('/system-audits/'.$auditId);

        $actions = static::getContainer()->get(CorrectiveActionRepository::class);
        self::assertCount(1, $actions->findAll(), 'a draft corrective action is seeded for the finding');

        // Running it again creates nothing new (the finding already has an action).
        $client->request('GET', '/system-audits/'.$auditId);
        $client->submitForm('Generar plan de acciones');
        self::assertCount(1, $actions->findAll(), 'generation is idempotent');
    }

    public function testGenerateActionPlanRequiresNonConformityWrite(): void
    {
        // loggedInClient has SYSTEM_AUDIT write but no NONCONFORMITY permission.
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $audit = (new SystemAudit())->setYear(2026)->setType(AuditType::INTERNAL)->setAuditor('Auditora interna');
        $em->persist($audit);
        $em->flush();

        // The permission check runs before CSRF, so a missing token still yields 403 by permission.
        $client->request('POST', '/system-audits/'.$audit->getId().'/action-plan');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegisteringNonConformityFromAnAuditPrefillsLinkAndOrigin(): void
    {
        $client = $this->clientWithAuditAndNonConformityWrite();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $audit = (new SystemAudit())->setYear(2026)->setType(AuditType::EXTERNAL)->setAuditor('Entidad certificadora');
        $em->persist($audit);
        $em->flush();

        $client->request('GET', '/non-conformities/new?audit='.$audit->getId());

        self::assertResponseIsSuccessful();
        // Origin defaulted from the audit type, and the audit preselected in its dropdown.
        self::assertSelectorExists('#non_conformity_origin option[value="external_audit"][selected]');
        self::assertSelectorExists('#non_conformity_audit option[value="'.$audit->getId().'"][selected]');
    }

    /**
     * Logged-in client whose role can write both audits and non-conformities (needed to seed the
     * action plan from an audit).
     */
    private function clientWithAuditAndNonConformityWrite(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('audit-nc')->setName('Auditorías y NC')
            ->setLevel(Area::SYSTEM_AUDIT, PermissionLevel::WRITE)
            ->setLevel(Area::NONCONFORMITY, PermissionLevel::WRITE);
        $em->persist($role);

        $user = (new User())->setFullName('Tester')->setEmail('audit-nc-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
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
