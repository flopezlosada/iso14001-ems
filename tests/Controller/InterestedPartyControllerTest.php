<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\InterestedParty;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\InterestedPartyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the annual interested-parties register UI (F.04.0 / PPI).
 * Routes require an authenticated user with INTERESTED_PARTY permission; each test logs one in.
 * Database writes are rolled back after each test by DAMA DoctrineTestBundle.
 */
final class InterestedPartyControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('partes')->setName('Gestión de partes interesadas')->setLevel(Area::INTERESTED_PARTY, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('partes-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testYearPageRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/interested-parties/2025');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Partes interesadas 2025');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/interested-parties/2025/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input#interested_party_name');
    }

    public function testSubmittingValidPartyPersistsItAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/interested-parties/2025/new');

        // Realistic data shape: a party whose incidents column is left blank (it stays null),
        // mirroring most rows of the real F.04.0 sheet.
        $client->submitForm('Guardar', [
            'interested_party[name]' => 'Proveedores',
            'interested_party[needsAndExpectations]' => 'Cumplimiento de cláusulas y puntualidad en los pagos.',
            'interested_party[incidents]' => '',
        ]);

        self::assertResponseRedirects('/interested-parties/2025');

        $parties = static::getContainer()->get(InterestedPartyRepository::class)->findForYear(2025);
        self::assertCount(1, $parties);
        $party = $parties[0];
        self::assertSame('Proveedores', $party->getName());
        // A blank incidents field is stored as null, not an empty string.
        self::assertNull($party->getIncidents());

        // The creation is recorded in the activity trail.
        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'interestedparty.created'])
        );

        // Following the redirect, the new party is listed in the year's table.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Proveedores');
    }

    public function testSubmittingInvalidPartyRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/interested-parties/2025/new');

        // Blank required fields: the form must be re-rendered with errors, not persisted.
        $client->submitForm('Guardar', [
            'interested_party[name]' => '',
            'interested_party[needsAndExpectations]' => '',
            'interested_party[incidents]' => '',
        ]);

        // An invalid submission re-renders the form (Symfony returns HTTP 422, not a redirect).
        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
        self::assertCount(0, static::getContainer()->get(InterestedPartyRepository::class)->findForYear(2025));
    }

    public function testEditingPartyOfAnotherYearIsNotFound(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $party = (new InterestedParty())
            ->setReviewYear(2025)
            ->setName('Dirección')
            ->setNeedsAndExpectations('Buena imagen del centro.');
        $em->persist($party);
        $em->flush();

        // The party belongs to 2025; requesting it under 2024 must 404.
        $client->request('GET', sprintf('/interested-parties/2024/%d/edit', $party->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingPartyRemovesItAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $party = (new InterestedParty())
            ->setReviewYear(2025)
            ->setName('Gestores de residuos')
            ->setNeedsAndExpectations('Residuos segregados correctamente.');
        $em->persist($party);
        $em->flush();
        $id = $party->getId();

        // Submit the delete form from the listing (its CSRF token is carried automatically).
        $client->request('GET', '/interested-parties/2025');
        $client->submitForm('Eliminar');

        self::assertResponseRedirects('/interested-parties/2025');
        self::assertNull(static::getContainer()->get(InterestedPartyRepository::class)->find($id));

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'interestedparty.deleted'])
        );
    }

    public function testCopyFromPreviousYearIntoEmptyYearBringsAll(): void
    {
        $client = $this->loggedInClient();
        $this->persistParty(2024, 'Usuarios/Alumnos', 'Atención cercana.', 'NO');
        $this->persistParty(2024, 'Proveedores', 'Puntualidad en pagos.', null);

        $client->request('GET', '/interested-parties/2025');
        $client->submitForm('Copiar de 2024');

        self::assertResponseRedirects('/interested-parties/2025');

        $copied = static::getContainer()->get(InterestedPartyRepository::class)->findForYear(2025);
        self::assertCount(2, $copied);
        $names = array_map(static fn (InterestedParty $p): string => $p->getName(), $copied);
        self::assertEqualsCanonicalizing(['Usuarios/Alumnos', 'Proveedores'], $names);
        // The free-text incidents value is carried over, including a null one.
        $byName = [];
        foreach ($copied as $p) {
            $byName[$p->getName()] = $p->getIncidents();
        }
        self::assertSame('NO', $byName['Usuarios/Alumnos']);
        self::assertNull($byName['Proveedores']);

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'interestedparty.copied_from_previous'])
        );
    }

    public function testCopyFromPreviousYearOnlyBringsMissingOnes(): void
    {
        $client = $this->loggedInClient();
        // Previous year has two parties; current year already has one of them (with its own text).
        $this->persistParty(2024, 'Proveedores', 'Texto del año anterior.', null);
        $this->persistParty(2024, 'Dirección', 'Buena imagen.', null);
        $this->persistParty(2025, 'Proveedores', 'Texto YA editado en el año actual.', 'Sí');

        $client->request('GET', '/interested-parties/2025');
        $client->submitForm('Copiar de 2024');

        $current = static::getContainer()->get(InterestedPartyRepository::class)->findForYear(2025);
        // Only "Dirección" is brought over; "Proveedores" is not duplicated.
        self::assertCount(2, $current);
        $names = array_map(static fn (InterestedParty $p): string => $p->getName(), $current);
        self::assertEqualsCanonicalizing(['Proveedores', 'Dirección'], $names);
        // The pre-existing "Proveedores" keeps its own edited text (not overwritten by the copy).
        $proveedores = array_values(array_filter($current, static fn (InterestedParty $p): bool => 'Proveedores' === $p->getName()))[0];
        self::assertSame('Texto YA editado en el año actual.', $proveedores->getNeedsAndExpectations());
    }

    public function testCopyFromPreviousYearWhenNothingNewCopiesNothing(): void
    {
        $client = $this->loggedInClient();
        $this->persistParty(2024, 'Proveedores', 'Texto.', null);
        $this->persistParty(2025, 'proveedores', 'Mismo concepto, distinto texto.', null);

        $client->request('GET', '/interested-parties/2025');
        $client->submitForm('Copiar de 2024');

        // Names match case-insensitively, so nothing new is copied: still a single party in 2025.
        self::assertCount(1, static::getContainer()->get(InterestedPartyRepository::class)->findForYear(2025));
    }

    /**
     * Persists an interested party for a year, for arranging the copy-from-previous-year scenarios.
     */
    private function persistParty(int $year, string $name, string $needs, ?string $incidents): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $party = (new InterestedParty())
            ->setReviewYear($year)
            ->setName($name)
            ->setNeedsAndExpectations($needs)
            ->setIncidents($incidents);
        $em->persist($party);
        $em->flush();
    }
}
