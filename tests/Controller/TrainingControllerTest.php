<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\TrainingAction;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\TrainingType;
use App\Repository\AuditLogRepository;
use App\Repository\TrainingActionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the annual training plan UI (F.03.0). Routes require an
 * authenticated user with TRAINING permission; each test logs one in. Database writes are rolled
 * back after each test by DAMA DoctrineTestBundle.
 */
final class TrainingControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('formacion')->setName('Gestión de formación')->setLevel(Area::TRAINING, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('formacion-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testYearPageRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training/2026');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Plan de formación 2026');
    }

    public function testShowRendersActionDetailWithStatusBadge(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // A delivered-but-not-yet-evaluated action: its derived status must be "Pendiente de evaluar".
        $action = (new TrainingAction())
            ->setPlanYear(2026)
            ->setDescription('Curso de gestión de residuos')
            ->setType(TrainingType::INTERNAL)
            ->setTargetAudience('Personal de limpieza')
            ->setObjectives('Segregar correctamente los residuos peligrosos.')
            ->setMethodology('Sesión presencial con demostración.')
            ->setPlannedDate(new \DateTimeImmutable('2026-10-30'))
            ->setActualDate(new \DateTimeImmutable('2026-11-05'));
        $em->persist($action);
        $em->flush();

        $client->request('GET', '/training/2026/'.$action->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Curso de gestión de residuos');
        self::assertSelectorTextContains('.badge', 'Pendiente de evaluar');
    }

    public function testShowRejectsActionFromAnotherYear(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $action = (new TrainingAction())
            ->setPlanYear(2025)
            ->setDescription('Curso previo')
            ->setTargetAudience('Profesorado')
            ->setObjectives('Objetivos')
            ->setMethodology('Metodología')
            ->setPlannedDate(new \DateTimeImmutable('2025-05-01'));
        $em->persist($action);
        $em->flush();

        // The year in the URL must match the action's plan year.
        $client->request('GET', '/training/2026/'.$action->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testNewActionFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training/2026/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('select#training_action_type');
    }

    public function testSubmittingValidActionPersistsItAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training/2026/new');

        // Realistic data shape: a planned action that has not happened yet, so both the actual
        // execution date and the efficacy evaluation are left empty (they stay null in the DB).
        $client->submitForm('Guardar', [
            'training_action[description]' => 'Curso ISO 14001',
            'training_action[type]' => 'int',
            'training_action[targetAudience]' => 'Profesorado',
            'training_action[objectives]' => 'Conocer el sistema ISO 14001 y sus aplicaciones',
            'training_action[plannedDate]' => '2026-10-30',
            'training_action[methodology]' => 'Difusión de vídeo y claustro informativo',
            'training_action[actualDate]' => '',
            'training_action[efficacyEvaluation]' => '',
        ]);

        self::assertResponseRedirects('/training/2026');

        $actions = static::getContainer()->get(TrainingActionRepository::class)->findForYear(2026);
        self::assertCount(1, $actions);
        $action = $actions[0];
        self::assertSame('Curso ISO 14001', $action->getDescription());
        // The not-yet-executed action keeps its nullable fields empty.
        self::assertNull($action->getActualDate());
        self::assertNull($action->getEfficacyEvaluation());

        // The creation is recorded in the activity trail.
        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'training.created'])
        );

        // Following the redirect, the new action is listed in the year's table.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Curso ISO 14001');
    }

    public function testSubmittingInvalidActionRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training/2026/new');

        // Blank required text fields (planned date kept valid, like the other modules' tests):
        // the form must be re-rendered with errors, not persisted.
        $client->submitForm('Guardar', [
            'training_action[description]' => '',
            'training_action[type]' => 'int',
            'training_action[targetAudience]' => '',
            'training_action[objectives]' => '',
            'training_action[plannedDate]' => '2026-10-30',
            'training_action[methodology]' => '',
        ]);

        // An invalid submission re-renders the form (Symfony returns HTTP 422, not a redirect).
        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
        self::assertCount(0, static::getContainer()->get(TrainingActionRepository::class)->findForYear(2026));
    }
}
