<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\EnvironmentalAspectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the environmental aspect catalogue. Routes require write
 * access to the aspect area. Database writes are rolled back after each test by DAMA.
 */
final class EnvironmentalAspectControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('aspectos')->setName('Gestión de aspectos')->setLevel(Area::ASPECT, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('aspect-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/aspects');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Aspectos ambientales');
    }

    public function testIndexShowsWorkflowGuide(): void
    {
        $client = $this->loggedInClient();
        $crawler = $client->request('GET', '/aspects');

        self::assertResponseIsSuccessful();
        // The "qué falta este curso" guide renders its checklist of pending work.
        self::assertSelectorExists('.workflow-guide .workflow-steps');
        self::assertSelectorTextContains('.workflow-steps', 'Identificar los aspectos');
        self::assertSelectorTextContains('.workflow-steps', 'Evaluar este año');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/aspects/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#environmental_aspect_category');
    }

    public function testSubmittingValidAspectPersistsItAndRedirectsToDetail(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/aspects/new');
        $client->submitForm('Guardar', [
            'environmental_aspect[name]' => 'Electricidad',
            'environmental_aspect[category]' => 'consumption',
            'environmental_aspect[unit]' => 'kWh',
        ]);

        $aspect = static::getContainer()->get(EnvironmentalAspectRepository::class)->findOneBy([]);
        self::assertNotNull($aspect);
        self::assertResponseRedirects('/aspects/'.$aspect->getId());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'aspect.created'])
        );
    }

    public function testSubmittingWithoutNameRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/aspects/new');
        $client->submitForm('Guardar', [
            'environmental_aspect[name]' => '',
            'environmental_aspect[category]' => 'waste',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
