<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DafoAnalysis;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\DafoAnalysisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the DAFO UI. Routes require per-area access; each test logs
 * in a user with the level it needs. Database writes are rolled back after each test by DAMA
 * DoctrineTestBundle.
 */
final class DafoControllerTest extends WebTestCase
{
    /**
     * Builds a client logged in as a user holding the given permission level over the DAFO area.
     */
    private function loggedInClient(PermissionLevel $level = PermissionLevel::WRITE): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('dafo-role')->setName('Gestión del DAFO')->setLevel(Area::DAFO, $level);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('dafo-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    /**
     * Persists a DAFO analysis for the given school year and returns it.
     */
    private function seedAnalysis(string $schoolYear): DafoAnalysis
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $analysis = new DafoAnalysis();
        $analysis->setSchoolYear($schoolYear)->setWeaknesses('Algo');
        $em->persist($analysis);
        $em->flush();

        return $analysis;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/dafo');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Análisis DAFO');
    }

    public function testShowRendersQuadrantsAsListsForReadOnlyUser(): void
    {
        $client = $this->loggedInClient(PermissionLevel::READ);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $analysis = new DafoAnalysis();
        $analysis->setSchoolYear('2025-2026')
            ->setWeaknesses("Falta de formación ambiental.\n\nBurocracia.\n")
            ->setOpportunities('Prestigio por la certificación.');
        $em->persist($analysis);
        $em->flush();

        $crawler = $client->request('GET', '/dafo/'.$analysis->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Análisis DAFO 2025-2026');

        // One list item per non-blank line; the empty line between the two items is dropped.
        self::assertCount(2, $crawler->filter('.dafo-cell.internal.negative li'));
        self::assertSelectorTextContains('.dafo-cell.internal.negative li', 'Falta de formación ambiental.');

        // Empty quadrants (threats and strengths are null here) show the placeholder, not a list.
        self::assertSelectorTextContains('.dafo-cell.external.negative', 'Sin elementos.');
        self::assertCount(0, $crawler->filter('.dafo-cell.external.negative li'));
        self::assertSelectorTextContains('.dafo-cell.internal.positive', 'Sin elementos.');
    }

    public function testShowOnMissingAnalysisReturns404(): void
    {
        $client = $this->loggedInClient(PermissionLevel::READ);
        $client->request('GET', '/dafo/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testShowDeniedForUserWithoutDafoAccess(): void
    {
        $client = $this->loggedInClient(PermissionLevel::NONE);
        $analysis = $this->seedAnalysis('2025-2026');

        $client->request('GET', '/dafo/'.$analysis->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexExerciseLinksToDetail(): void
    {
        $client = $this->loggedInClient(PermissionLevel::READ);
        $analysis = $this->seedAnalysis('2025-2026');
        self::assertNotNull($analysis->getId());

        $crawler = $client->request('GET', '/dafo');

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('a.cell-link');
        self::assertCount(1, $link);
        self::assertSame('/dafo/'.$analysis->getId(), $link->attr('href'));
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/dafo/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input#dafo_analysis_schoolYear');
    }

    public function testSubmittingValidAnalysisPersistsAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/dafo/new');
        $client->submitForm('Guardar', [
            'dafo_analysis[schoolYear]' => '2025-2026',
            'dafo_analysis[weaknesses]' => "Falta de formación ambiental.\nBurocracia.",
            'dafo_analysis[opportunities]' => 'Prestigio por la certificación.',
        ]);

        $analysis = static::getContainer()->get(DafoAnalysisRepository::class)->findOneBy(['schoolYear' => '2025-2026']);
        self::assertNotNull($analysis);
        self::assertResponseRedirects('/dafo/'.$analysis->getId());
        self::assertSame('2025-2026', $analysis->getSchoolYear());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'dafo.created'])
        );
    }

    public function testDuplicateSchoolYearRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $this->seedAnalysis('2025-2026');

        $client->request('GET', '/dafo/new');
        $client->submitForm('Guardar', [
            'dafo_analysis[schoolYear]' => '2025-2026',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }

    public function testInvalidSchoolYearFormatDoesNotPersist(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/dafo/new');
        $client->submitForm('Guardar', [
            'dafo_analysis[schoolYear]' => '2025',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertNull(static::getContainer()->get(DafoAnalysisRepository::class)->findOneBy(['schoolYear' => '2025']));
    }

    public function testReadOnlyUserCannotCreate(): void
    {
        $client = $this->loggedInClient(PermissionLevel::READ);
        $client->request('POST', '/dafo/new', [
            'dafo_analysis' => ['schoolYear' => '2025-2026'],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNull(static::getContainer()->get(DafoAnalysisRepository::class)->findOneBy([]));
    }

    public function testDeleteWithValidTokenRemovesAndAudits(): void
    {
        $client = $this->loggedInClient();
        $analysis = $this->seedAnalysis('2025-2026');
        $id = $analysis->getId();

        $client->request('GET', '/dafo');
        $client->submitForm('Borrar');

        self::assertResponseRedirects('/dafo');
        self::assertNull(static::getContainer()->get(DafoAnalysisRepository::class)->find($id));
        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'dafo.deleted'])
        );
    }

    public function testCloneToNextYearCreatesEditableDraftCopy(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // A realistic source: some quadrants filled, one (strengths) left empty/null.
        $source = new DafoAnalysis();
        $source->setSchoolYear('2024-2025')
            ->setWeaknesses("Falta de formación.\nBurocracia.")
            ->setThreats('Normativa cambiante.')
            ->setOpportunities('Prestigio por la certificación.');
        $em->persist($source);
        $em->flush();
        $sourceId = $source->getId();

        $client->request('GET', '/dafo');
        $client->submitForm('Clonar para 2025-2026');

        $repository = static::getContainer()->get(DafoAnalysisRepository::class);
        $copy = $repository->findOneBy(['schoolYear' => '2025-2026']);
        self::assertNotNull($copy);
        self::assertResponseRedirects('/dafo/'.$copy->getId().'/edit');

        // The quadrants are carried over verbatim into the new draft; the empty one stays null.
        self::assertSame("Falta de formación.\nBurocracia.", $copy->getWeaknesses());
        self::assertSame('Normativa cambiante.', $copy->getThreats());
        self::assertNull($copy->getStrengths());
        self::assertSame('Prestigio por la certificación.', $copy->getOpportunities());
        self::assertNotSame($sourceId, $copy->getId());

        // The source year is left untouched.
        $reloadedSource = $repository->find($sourceId);
        self::assertNotNull($reloadedSource);
        self::assertSame('2024-2025', $reloadedSource->getSchoolYear());
        self::assertSame("Falta de formación.\nBurocracia.", $reloadedSource->getWeaknesses());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'dafo.cloned_from_previous'])
        );
    }

    public function testCloneToNextYearDoesNotOverwriteExisting(): void
    {
        $client = $this->loggedInClient();
        $source = $this->seedAnalysis('2024-2025');

        // Render the listing while the next year is still free, so the clone form (with a valid CSRF
        // token) is present; then create the conflicting year before submitting.
        $client->request('GET', '/dafo');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $existing = new DafoAnalysis();
        $existing->setSchoolYear('2025-2026')->setWeaknesses('Contenido ya editado.');
        $em->persist($existing);
        $em->flush();
        $existingId = $existing->getId();

        $client->submitForm('Clonar para 2025-2026');

        self::assertResponseRedirects('/dafo');

        $repository = static::getContainer()->get(DafoAnalysisRepository::class);
        // Still exactly two analyses: the clone was refused, nothing duplicated.
        self::assertCount(2, $repository->findAll());
        // The pre-existing 2025-2026 keeps its own content (not overwritten by the source's quadrants).
        $reloaded = $repository->find($existingId);
        self::assertNotNull($reloaded);
        self::assertSame('Contenido ya editado.', $reloaded->getWeaknesses());
        self::assertSame('Algo', $source->getWeaknesses());

        self::assertNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'dafo.cloned_from_previous'])
        );
    }

    public function testCloneButtonHiddenWhenNextYearAlreadyExists(): void
    {
        $client = $this->loggedInClient();
        $this->seedAnalysis('2024-2025');
        $this->seedAnalysis('2025-2026');

        $crawler = $client->request('GET', '/dafo');

        self::assertResponseIsSuccessful();
        // 2024-2025 -> next 2025-2026 exists (hidden); 2025-2026 -> next 2026-2027 free (shown). One button.
        self::assertCount(1, $crawler->filter('button:contains("Clonar para 2026-2027")'));
        self::assertCount(0, $crawler->filter('button:contains("Clonar para 2025-2026")'));
    }

    public function testReadOnlyUserCannotClone(): void
    {
        $client = $this->loggedInClient(PermissionLevel::READ);
        $source = $this->seedAnalysis('2024-2025');

        $client->request('POST', '/dafo/'.$source->getId().'/clone-next');

        self::assertResponseStatusCodeSame(403);
        self::assertNull(static::getContainer()->get(DafoAnalysisRepository::class)->findOneBy(['schoolYear' => '2025-2026']));
    }

    public function testCloneWithoutTokenDoesNotCreate(): void
    {
        $client = $this->loggedInClient();
        $source = $this->seedAnalysis('2024-2025');

        $client->request('POST', '/dafo/'.$source->getId().'/clone-next');

        self::assertResponseStatusCodeSame(403);
        self::assertNull(static::getContainer()->get(DafoAnalysisRepository::class)->findOneBy(['schoolYear' => '2025-2026']));
    }

    public function testDeleteWithoutTokenDoesNotRemove(): void
    {
        $client = $this->loggedInClient();
        $analysis = $this->seedAnalysis('2025-2026');
        $id = $analysis->getId();

        $client->request('POST', '/dafo/'.$id.'/delete');

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull(static::getContainer()->get(DafoAnalysisRepository::class)->find($id));
    }
}
