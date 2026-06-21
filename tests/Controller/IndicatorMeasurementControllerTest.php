<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Indicator;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\SgmaProcess;
use App\Repository\IndicatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for indicator measurements, nested under an indicator.
 * Database writes are rolled back after each test by DAMA.
 */
final class IndicatorMeasurementControllerTest extends WebTestCase
{
    /**
     * @return array{0: KernelBrowser, 1: int} [client, indicatorId]
     */
    private function scenario(): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('indicadores')->setName('Gestión de indicadores')->setLevel(Area::INDICATOR, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Tester')->setEmail('imeas-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $indicator = (new Indicator())->setName('Consumo de energía')->setProcess(SgmaProcess::PERFORMANCE_EVALUATION);
        $em->persist($indicator);
        $em->flush();
        $client->loginUser($user);

        return [$client, $indicator->getId()];
    }

    public function testNewMeasurementFormRenders(): void
    {
        [$client, $indicatorId] = $this->scenario();
        $client->request('GET', '/indicators/'.$indicatorId.'/measurements/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testSubmittingMeasurementPersistsItAndRedirects(): void
    {
        [$client, $indicatorId] = $this->scenario();
        $client->request('GET', '/indicators/'.$indicatorId.'/measurements/new');
        $client->submitForm('Guardar', [
            'indicator_measurement[year]' => '2026',
            'indicator_measurement[month]' => '3',
            'indicator_measurement[value]' => '37481',
        ]);

        self::assertResponseRedirects('/indicators/'.$indicatorId);

        $indicator = static::getContainer()->get(IndicatorRepository::class)->find($indicatorId);
        self::assertNotNull($indicator);
        self::assertCount(1, $indicator->getMeasurements());
    }

    public function testBreachedMeasurementShowsOpenNonConformityLink(): void
    {
        [$client, $indicatorId] = $this->scenario();
        $client->request('GET', '/indicators/'.$indicatorId.'/measurements/new');
        $client->submitForm('Guardar', [
            'indicator_measurement[year]' => '2026',
            'indicator_measurement[month]' => '4',
            'indicator_measurement[value]' => '99999',
            'indicator_measurement[breached]' => '1',
        ]);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="/non-conformities/new"]');
    }

    public function testSubmittingInvalidValueRedisplaysFormWithErrors(): void
    {
        [$client, $indicatorId] = $this->scenario();
        $client->request('GET', '/indicators/'.$indicatorId.'/measurements/new');
        $client->submitForm('Guardar', [
            'indicator_measurement[year]' => '2026',
            'indicator_measurement[month]' => '5',
            'indicator_measurement[value]' => 'abc',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
