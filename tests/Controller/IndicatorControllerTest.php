<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\IndicatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the indicator catalogue. Routes require write access to
 * the indicator area. Database writes are rolled back after each test by DAMA.
 */
final class IndicatorControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('indicadores')->setName('Gestión de indicadores')->setLevel(Area::INDICATOR, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('indicator-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/indicators');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Indicadores');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/indicators/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#indicator_process');
        // Per-field contextual help renders on the real form (guards against a slug typo).
        self::assertSelectorExists('.help-field-label a.help-btn[data-help="indicador-referencia"]');
    }

    public function testSubmittingValidIndicatorPersistsItAndRedirectsToDetail(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/indicators/new');
        $client->submitForm('Guardar', [
            'indicator[name]' => 'Consumo de energía',
            'indicator[process]' => 'performance_evaluation',
            'indicator[referenceValue]' => '5%',
            'indicator[periodicity]' => 'monthly',
        ]);

        $indicator = static::getContainer()->get(IndicatorRepository::class)->findOneBy([]);
        self::assertNotNull($indicator);
        self::assertResponseRedirects('/indicators/'.$indicator->getId());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'indicator.created'])
        );
    }

    public function testSubmittingWithoutNameRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/indicators/new');
        $client->submitForm('Guardar', [
            'indicator[name]' => '',
            'indicator[process]' => 'resources',
            'indicator[periodicity]' => 'annual',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
