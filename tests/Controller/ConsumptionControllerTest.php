<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\ConsumptionType;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\ConsumptionReadingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Functional (FE + BE) smoke tests for the consumption capture UI. Routes require an
 * authenticated user; each test logs one in. Database writes are rolled back after each test
 * by DAMA DoctrineTestBundle.
 */
final class ConsumptionControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('consumos')->setName('Gestión de consumos')->setLevel(Area::CONSUMPTION, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('consumo-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testYearPageRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/consumption/2026');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Consumos 2026');
    }

    public function testNewReadingFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/consumption/2026/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('select#consumption_reading_type');
    }

    public function testSubmittingValidReadingPersistsItAndRedirects(): void
    {
        $client = $this->loggedInClient();
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

        // The creation is recorded in the activity trail.
        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'consumption.created'])
        );

        // Following the redirect, the new reading is listed in the year's table.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Agua');
    }

    public function testSubmittingInvalidReadingRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/consumption/2026/new');
        $client->submitForm('Guardar', [
            'consumption_reading[type]' => 'electricity',
            'consumption_reading[periodMonth]' => '3',
            'consumption_reading[quantity]' => 'abc',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }

    public function testNewFormRendersInvoiceFileInput(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/consumption/2026/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[enctype="multipart/form-data"]');
        self::assertSelectorExists('input[type="file"]#consumption_reading_invoiceFile');
    }

    public function testUploadingInvoiceStoresItAndIsDownloadable(): void
    {
        $client = $this->loggedInClient();
        $crawler = $client->request('GET', '/consumption/2026/new');

        $form = $crawler->selectButton('Guardar')->form([
            'consumption_reading[type]' => 'water',
            'consumption_reading[periodMonth]' => '6',
            'consumption_reading[quantity]' => '12.5',
            'consumption_reading[cost]' => '40.00',
        ]);
        // The form's ArrayAccess returns FormField|FormField[]; narrow to FileFormField so the
        // upload() call is type-safe (keeps PHPStan happy).
        $invoiceField = $form['consumption_reading[invoiceFile]'];
        \assert($invoiceField instanceof FileFormField);
        $invoiceField->upload($this->pdfFixture('factura-junio.pdf'));
        $client->submit($form);

        self::assertResponseRedirects('/consumption/2026');

        $reading = static::getContainer()->get(ConsumptionReadingRepository::class)
            ->findOneByPeriod(ConsumptionType::WATER, 2026, 6);
        self::assertNotNull($reading);
        self::assertTrue($reading->hasInvoice());
        self::assertSame('factura-junio.pdf', $reading->getInvoiceOriginalName());

        // The stored invoice can be downloaded back.
        $client->request('GET', '/consumption/2026/'.$reading->getId().'/invoice');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('factura-junio.pdf', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }

    /**
     * Creates a throwaway PDF file whose basename is $name, to feed the upload field.
     */
    private function pdfFixture(string $name): string
    {
        $dir = sys_get_temp_dir().'/consumption-test-fixtures';
        (new Filesystem())->mkdir($dir);
        $path = $dir.'/'.$name;
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");

        return $path;
    }

    protected function tearDown(): void
    {
        // Remove files written during the test: the redirected uploads dir and the fixtures.
        $filesystem = new Filesystem();
        if (static::getContainer()->hasParameter('app.uploads_dir')) {
            $filesystem->remove(static::getContainer()->getParameter('app.uploads_dir'));
        }
        $filesystem->remove(sys_get_temp_dir().'/consumption-test-fixtures');

        parent::tearDown();
    }
}
