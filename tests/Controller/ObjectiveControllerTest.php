<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\ObjectiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the objectives UI. Routes require write access to the
 * objective area. Database writes are rolled back after each test by DAMA.
 */
final class ObjectiveControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('objetivos')->setName('Gestión de objetivos')->setLevel(Area::OBJECTIVE, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('objective-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Objetivos');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#objective_status');
    }

    public function testSubmittingValidObjectiveAutogeneratesReferenceAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/new');
        $client->submitForm('Guardar', [
            'objective[description]' => 'Reducir el consumo de agua en un 5%',
            'objective[targetPeriod]' => 'enero 2026 a diciembre 2026',
            'objective[status]' => 'in_progress',
        ]);

        self::assertResponseRedirects('/objectives');

        $objective = static::getContainer()->get(ObjectiveRepository::class)->findOneBy([]);
        self::assertNotNull($objective);
        self::assertSame('OBJ-01', $objective->getReference());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'objective.created'])
        );
    }

    public function testNotAchievedObjectiveShowsOpenNonConformityLink(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/new');
        $client->submitForm('Guardar', [
            'objective[description]' => 'Reducir el consumo energético en un 5%',
            'objective[status]' => 'not_achieved',
        ]);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="/non-conformities/new"]');
    }

    public function testSubmittingWithoutDescriptionRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/new');
        $client->submitForm('Guardar', [
            'objective[description]' => '',
            'objective[status]' => 'in_progress',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
