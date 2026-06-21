<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\NonConformityStatus;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\NonConformityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the non-conformity UI. Routes require write access to the
 * non-conformity area; each test logs in a user that has it. Database writes are rolled back
 * after each test by DAMA DoctrineTestBundle.
 */
final class NonConformityControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('nc')->setName('Gestión de no conformidades')->setLevel(Area::NONCONFORMITY, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester NC')->setEmail('nc-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/non-conformities');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'No conformidades');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/non-conformities/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('select#non_conformity_origin');
    }

    public function testSubmittingValidNonConformityAutogeneratesReferenceAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/non-conformities/new');
        $client->submitForm('Guardar', [
            'non_conformity[openedAt]' => '2026-03-15',
            'non_conformity[origin]' => 'external_audit',
            'non_conformity[description]' => 'No hay evidencia de la comunicación previa de residuos peligrosos.',
            'non_conformity[status]' => 'open',
        ]);

        self::assertResponseRedirects('/non-conformities');

        $nc = static::getContainer()->get(NonConformityRepository::class)->findOneBy([]);
        self::assertNotNull($nc);
        self::assertSame('NC.AE.2026.01', $nc->getReference());
        self::assertSame(NonConformityStatus::OPEN, $nc->getStatus());
        self::assertNull($nc->getClosedAt());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'nonconformity.created'])
        );

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'NC.AE.2026.01');
    }

    public function testSequenceIncrementsWithinSameOriginAndYear(): void
    {
        $client = $this->loggedInClient();

        foreach (['Primera no conformidad', 'Segunda no conformidad'] as $description) {
            $client->request('GET', '/non-conformities/new');
            $client->submitForm('Guardar', [
                'non_conformity[openedAt]' => '2026-05-01',
                'non_conformity[origin]' => 'external_audit',
                'non_conformity[description]' => $description,
                'non_conformity[status]' => 'open',
            ]);
        }

        $references = array_map(
            static fn ($nc): string => $nc->getReference(),
            static::getContainer()->get(NonConformityRepository::class)->findAllOrdered(),
        );

        self::assertContains('NC.AE.2026.01', $references);
        self::assertContains('NC.AE.2026.02', $references);
    }

    public function testClosingStampsClosingDate(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/non-conformities/new');
        $client->submitForm('Guardar', [
            'non_conformity[openedAt]' => '2026-01-10',
            'non_conformity[origin]' => 'internal',
            'non_conformity[description]' => 'Incumplimiento interno detectado.',
            'non_conformity[status]' => 'closed',
        ]);

        $nc = static::getContainer()->get(NonConformityRepository::class)->findOneBy([]);
        self::assertNotNull($nc);
        self::assertSame(NonConformityStatus::CLOSED, $nc->getStatus());
        self::assertNotNull($nc->getClosedAt());
    }

    public function testSubmittingWithoutDescriptionRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/non-conformities/new');
        $client->submitForm('Guardar', [
            'non_conformity[openedAt]' => '2026-03-15',
            'non_conformity[origin]' => 'internal',
            'non_conformity[description]' => '',
            'non_conformity[status]' => 'open',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
