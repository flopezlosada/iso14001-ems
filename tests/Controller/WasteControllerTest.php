<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\WasteRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) tests for the waste register, including per-area authorization.
 */
final class WasteControllerTest extends WebTestCase
{
    private function clientWithWasteWrite(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = (new Role())->setCode('residuos')->setName('Gestión de residuos')->setLevel(Area::WASTE, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Tester')->setEmail('residuos-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testIndexIsForbiddenWithoutPermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setFullName('Sin permiso')->setEmail('noperm@example.test')->setActive(true);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/waste');
        self::assertResponseStatusCodeSame(403);
    }

    private function clientWithWasteReadOnly(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $role = (new Role())->setCode('residuos-lectura')->setName('Lectura de residuos')->setLevel(Area::WASTE, PermissionLevel::READ);
        $em->persist($role);
        $user = (new User())->setFullName('Solo lectura')->setEmail('residuos-lectura@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $client;
    }

    public function testNewIsForbiddenForReadOnlyUser(): void
    {
        $client = $this->clientWithWasteReadOnly();

        // The bare index redirects to the current year, which a read-only user may view.
        $client->request('GET', '/waste');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request('POST', '/waste/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexRedirectsToCurrentYear(): void
    {
        $client = $this->clientWithWasteWrite();
        $client->request('GET', '/waste');

        self::assertResponseRedirects('/waste/'.date('Y'));
    }

    public function testYearViewRenders(): void
    {
        $client = $this->clientWithWasteWrite();
        $client->request('GET', '/waste/'.date('Y'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Residuos');
    }

    public function testSubmittingValidRecordPersistsAndIsAudited(): void
    {
        $client = $this->clientWithWasteWrite();
        $client->request('GET', '/waste/new');
        self::assertResponseIsSuccessful();

        $client->submitForm('Guardar', [
            'waste_record[lerCode]' => '200121',
            'waste_record[description]' => 'Tubos fluorescentes',
            'waste_record[quantityKg]' => '12.5',
            'waste_record[pickupDate]' => '2026-05-20',
            'waste_record[manager]' => 'Gestor Autorizado SL',
        ]);

        // Lands on the year the record actually belongs to, not the bare index.
        self::assertResponseRedirects('/waste/2026');

        $record = static::getContainer()->get(WasteRecordRepository::class)->findOneBy(['lerCode' => '200121']);
        self::assertNotNull($record);
        self::assertSame('Gestor Autorizado SL', $record->getManager());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'waste.created'])
        );
    }

    public function testSubmittingRecordWithoutDateRedirectsToUndatedList(): void
    {
        $client = $this->clientWithWasteWrite();
        $client->request('GET', '/waste/new');

        // No pick-up date: the record cannot be placed in any year, so it lands on the undated list.
        $client->submitForm('Guardar', [
            'waste_record[lerCode]' => '200201',
            'waste_record[description]' => 'Restos vegetales (compostaje)',
            'waste_record[quantityKg]' => '',
            'waste_record[pickupDate]' => '',
            'waste_record[manager]' => '',
        ]);

        self::assertResponseRedirects('/waste/sin-fecha');

        $record = static::getContainer()->get(WasteRecordRepository::class)->findOneBy(['lerCode' => '200201']);
        self::assertNotNull($record);
        self::assertNull($record->getPickupDate());
    }

    public function testUndatedListRenders(): void
    {
        $client = $this->clientWithWasteReadOnly();
        $client->request('GET', '/waste/sin-fecha');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Residuos sin fecha');
    }

    public function testUndatedListIsForbiddenWithoutPermission(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setFullName('Sin permiso')->setEmail('noperm-undated@example.test')->setActive(true);
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/waste/sin-fecha');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSubmittingInvalidRecordShowsErrorsAndDoesNotPersist(): void
    {
        $client = $this->clientWithWasteWrite();
        $client->request('GET', '/waste/new');

        // Invalid LER code (not 6 digits) and blank required fields.
        $client->submitForm('Guardar', [
            'waste_record[lerCode]' => 'ABC',
            'waste_record[description]' => '',
            'waste_record[quantityKg]' => '',
            'waste_record[pickupDate]' => '2026-05-20',
            'waste_record[manager]' => '',
        ]);

        // An invalid submission re-renders the form (Symfony returns HTTP 422, not a redirect)
        // and surfaces the field errors, instead of persisting the record.
        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
        self::assertNull(
            static::getContainer()->get(WasteRecordRepository::class)->findOneBy(['lerCode' => 'ABC'])
        );
    }
}
