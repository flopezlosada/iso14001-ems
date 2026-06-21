<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\LegalRequirementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the legal requirements UI. Routes require write access to
 * the legal-requirement area; each test logs in a user that has it. Database writes are rolled
 * back after each test by DAMA DoctrineTestBundle.
 */
final class LegalRequirementControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('legal')->setName('Gestión de requisitos legales')->setLevel(Area::LEGAL_REQUIREMENT, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('legal-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/legal-requirements');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Requisitos legales');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/legal-requirements/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#legal_requirement_scope');
    }

    public function testSubmittingValidRequirementAutogeneratesReferenceAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/legal-requirements/new');
        $client->submitForm('Guardar', [
            'legal_requirement[legalProvision]' => 'Ley 7/2022 de residuos y suelos contaminados',
            'legal_requirement[scope]' => 'national',
            'legal_requirement[specificRequirement]' => 'Comunicación previa de producción de residuos peligrosos.',
            'legal_requirement[complianceStatus]' => 'pending',
        ]);

        self::assertResponseRedirects('/legal-requirements');

        $requirement = static::getContainer()->get(LegalRequirementRepository::class)->findOneBy([]);
        self::assertNotNull($requirement);
        self::assertSame('RL-01', $requirement->getReference());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'legalrequirement.created'])
        );
    }

    public function testSequenceIncrements(): void
    {
        $client = $this->loggedInClient();

        foreach (['Ley A', 'Ley B'] as $provision) {
            $client->request('GET', '/legal-requirements/new');
            $client->submitForm('Guardar', [
                'legal_requirement[legalProvision]' => $provision,
                'legal_requirement[scope]' => 'local',
                'legal_requirement[specificRequirement]' => 'Requisito de '.$provision,
                'legal_requirement[complianceStatus]' => 'compliant',
            ]);
        }

        $references = array_map(
            static fn ($r): string => $r->getReference(),
            static::getContainer()->get(LegalRequirementRepository::class)->findAllOrdered(),
        );

        self::assertSame(['RL-01', 'RL-02'], $references);
    }

    public function testNonCompliantRequirementShowsOpenNonConformityLink(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/legal-requirements/new');
        $client->submitForm('Guardar', [
            'legal_requirement[legalProvision]' => 'RD 513/2017 protección contra incendios',
            'legal_requirement[scope]' => 'national',
            'legal_requirement[specificRequirement]' => 'Inspecciones reglamentarias de PCI.',
            'legal_requirement[complianceStatus]' => 'non_compliant',
        ]);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="/non-conformities/new"]');
    }

    public function testSubmittingWithoutProvisionRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/legal-requirements/new');
        $client->submitForm('Guardar', [
            'legal_requirement[legalProvision]' => '',
            'legal_requirement[scope]' => 'local',
            'legal_requirement[specificRequirement]' => 'Algo',
            'legal_requirement[complianceStatus]' => 'pending',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
