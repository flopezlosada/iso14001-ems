<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\ConsumptionType;
use App\Repository\ConsumptionReadingRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the consumption capture UI.
 *
 * Database writes are rolled back automatically after each test by DAMA DoctrineTestBundle.
 */
final class ConsumptionControllerTest extends WebTestCase
{
    public function testYearPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/consumption/2026');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Consumos 2026');
    }

    public function testNewReadingFormRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/consumption/2026/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('select#consumption_reading_type');
    }

    public function testSubmittingValidReadingPersistsItAndRedirects(): void
    {
        $client = static::createClient();
        $client->request('GET', '/consumption/2026/new');
        $client->submitForm('Guardar', [
            'consumption_reading[type]' => 'water',
            'consumption_reading[periodMonth]' => '5',
            'consumption_reading[quantity]' => '123.5',
            'consumption_reading[cost]' => '250.00',
        ]);

        self::assertResponseRedirects('/consumption/2026');

        $reading = static::getContainer()->get(ConsumptionReadingRepository::class)
            ->findOneByPeriod(ConsumptionType::WATER, 2026, 5);
        self::assertNotNull($reading);
        self::assertSame('250.00', $reading->getCost());

        // Following the redirect, the new reading is listed in the year's table.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Agua');
    }

    public function testSubmittingInvalidReadingRedisplaysFormWithErrors(): void
    {
        $client = static::createClient();
        $client->request('GET', '/consumption/2026/new');
        $client->submitForm('Guardar', [
            'consumption_reading[type]' => 'electricity',
            'consumption_reading[periodMonth]' => '3',
            'consumption_reading[quantity]' => 'abc',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
