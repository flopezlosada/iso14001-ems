<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Objective;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\ObjectiveStatus;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\ObjectiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the per-course objectives UI (F.07.01). Routes require write
 * access to the objective area. Database writes are rolled back after each test by DAMA.
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

    public function testIndexRedirectsToACourse(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives');

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertMatchesRegularExpression(
            '#/objectives/\d{4}-\d{4}$#',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }

    public function testYearPageRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/2025-2026');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Objetivos 2025-2026');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/2025-2026/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#objective_status');
        // Per-field contextual help renders on the real form (guards against a slug typo).
        self::assertSelectorExists('.help-field-label a.help-btn[data-help="objetivo-cumplimiento"]');
    }

    public function testSubmittingValidObjectiveAutogeneratesReferenceAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/2025-2026/new');
        $client->submitForm('Guardar', [
            'objective[description]' => 'Reducir el consumo de agua en un 5%',
            'objective[targetPeriod]' => 'enero 2026 a diciembre 2026',
            'objective[status]' => 'in_progress',
        ]);

        $objective = static::getContainer()->get(ObjectiveRepository::class)->findOneBy([]);
        self::assertNotNull($objective);
        self::assertResponseRedirects('/objectives/2025-2026/'.$objective->getId());
        self::assertSame('OBJ-01', $objective->getReference());
        self::assertSame('2025-2026', $objective->getSchoolYear());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'objective.created'])
        );
    }

    public function testNotAchievedObjectiveShowsOpenNonConformityLink(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/objectives/2025-2026/new');
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
        $client->request('GET', '/objectives/2025-2026/new');
        $client->submitForm('Guardar', [
            'objective[description]' => '',
            'objective[status]' => 'in_progress',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }

    public function testEditingObjectiveOfAnotherCourseIsNotFound(): void
    {
        $client = $this->loggedInClient();
        $objective = $this->persistObjective('OBJ-01', 1, '2025-2026', 'Reducir el consumo de agua', ObjectiveStatus::IN_PROGRESS);

        // The objective belongs to 2025-2026; requesting it under 2024-2025 must 404.
        $client->request('GET', sprintf('/objectives/2024-2025/%d/edit', $objective->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testCopyFromPreviousCourseIntoEmptyCourseBringsAll(): void
    {
        $client = $this->loggedInClient();
        $this->persistObjective('OBJ-01', 1, '2024-2025', 'Reducir el consumo de agua un 5%', ObjectiveStatus::ACHIEVED);
        $this->persistObjective('OBJ-02', 2, '2024-2025', 'Campaña de concienciación ambiental', ObjectiveStatus::NOT_ACHIEVED);

        $client->request('GET', '/objectives/2025-2026');
        $client->submitForm('Copiar de 2024-2025');

        self::assertResponseRedirects('/objectives/2025-2026');

        $copied = static::getContainer()->get(ObjectiveRepository::class)->findForSchoolYear('2025-2026');
        self::assertCount(2, $copied);
        $descriptions = array_map(static fn (Objective $o): string => $o->getDescription(), $copied);
        self::assertEqualsCanonicalizing(
            ['Reducir el consumo de agua un 5%', 'Campaña de concienciación ambiental'],
            $descriptions,
        );
        // Copies are fresh drafts: status reset to in progress, each with its own new reference.
        foreach ($copied as $objective) {
            self::assertSame(ObjectiveStatus::IN_PROGRESS, $objective->getStatus());
            self::assertMatchesRegularExpression('/^OBJ-\d{2}$/', $objective->getReference());
        }

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'objective.copied_from_previous'])
        );
    }

    public function testCopyFromPreviousCourseOnlyBringsMissingOnes(): void
    {
        $client = $this->loggedInClient();
        $this->persistObjective('OBJ-01', 1, '2024-2025', 'Reducir el consumo de agua', ObjectiveStatus::ACHIEVED);
        $this->persistObjective('OBJ-02', 2, '2024-2025', 'Campaña de concienciación', ObjectiveStatus::ACHIEVED);
        // The current course already has the first one (with its own status).
        $this->persistObjective('OBJ-03', 3, '2025-2026', 'Reducir el consumo de agua', ObjectiveStatus::IN_PROGRESS);

        $client->request('GET', '/objectives/2025-2026');
        $client->submitForm('Copiar de 2024-2025');

        $current = static::getContainer()->get(ObjectiveRepository::class)->findForSchoolYear('2025-2026');
        // Only "Campaña de concienciación" is brought over; "Reducir el consumo de agua" is not duplicated.
        self::assertCount(2, $current);
        $descriptions = array_map(static fn (Objective $o): string => $o->getDescription(), $current);
        self::assertEqualsCanonicalizing(['Reducir el consumo de agua', 'Campaña de concienciación'], $descriptions);
    }

    public function testCopyFromPreviousCourseWhenNothingNewCopiesNothing(): void
    {
        $client = $this->loggedInClient();
        $this->persistObjective('OBJ-01', 1, '2024-2025', 'Reducir el consumo de agua', ObjectiveStatus::ACHIEVED);
        // Same concept, different casing/whitespace: matched case-insensitively, so nothing new.
        $this->persistObjective('OBJ-02', 2, '2025-2026', '  reducir el consumo de agua ', ObjectiveStatus::IN_PROGRESS);

        $client->request('GET', '/objectives/2025-2026');
        $client->submitForm('Copiar de 2024-2025');

        self::assertCount(1, static::getContainer()->get(ObjectiveRepository::class)->findForSchoolYear('2025-2026'));
    }

    public function testShowRendersObjectiveDetail(): void
    {
        $client = $this->loggedInClient();
        $objective = $this->persistObjective('OBJ-01', 1, '2025-2026', 'Reducir el consumo de agua un 5%', ObjectiveStatus::IN_PROGRESS);

        $client->request('GET', sprintf('/objectives/2025-2026/%d', $objective->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'OBJ-01');
        self::assertSelectorTextContains('.card', 'Reducir el consumo de agua un 5%');
        // Contextual help is wired on the detail (guards against a slug typo).
        self::assertSelectorExists('a.help-btn[data-help="objetivo-cumplimiento"]');
    }

    public function testShowOfANotAchievedObjectiveOffersOpeningANonConformity(): void
    {
        $client = $this->loggedInClient();
        $objective = $this->persistObjective('OBJ-01', 1, '2025-2026', 'Reducir el consumo energético', ObjectiveStatus::NOT_ACHIEVED);

        $client->request('GET', sprintf('/objectives/2025-2026/%d', $objective->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="/non-conformities/new"]');
    }

    public function testShowingObjectiveOfAnotherCourseIsNotFound(): void
    {
        $client = $this->loggedInClient();
        $objective = $this->persistObjective('OBJ-01', 1, '2025-2026', 'Reducir el consumo de agua', ObjectiveStatus::IN_PROGRESS);

        // The objective belongs to 2025-2026; requesting it under 2024-2025 must 404.
        $client->request('GET', sprintf('/objectives/2024-2025/%d', $objective->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Persists an objective for arranging the per-course and copy scenarios.
     */
    private function persistObjective(string $reference, int $sequence, string $schoolYear, string $description, ObjectiveStatus $status): Objective
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $objective = (new Objective())
            ->setReference($reference)
            ->setSequence($sequence)
            ->setSchoolYear($schoolYear)
            ->setDescription($description)
            ->setStatus($status);
        $em->persist($objective);
        $em->flush();

        return $objective;
    }
}
