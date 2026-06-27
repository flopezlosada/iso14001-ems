<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Entity\TrainingAction;
use App\Entity\TrainingEvidence;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\TrainingType;
use App\Repository\AuditLogRepository;
use App\Repository\TrainingEvidenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the training evidence register UI (§7.2/§7.3). Routes require
 * an authenticated user with TRAINING permission; each test logs one in. Database writes are rolled
 * back after each test by DAMA DoctrineTestBundle.
 */
final class TrainingEvidenceControllerTest extends WebTestCase
{
    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('formacion')->setName('Gestión de formación')->setLevel(Area::TRAINING, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('training-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training-evidences');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Evidencias de formación');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training-evidences/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input#training_evidence_personName');
    }

    public function testSubmittingValidEvidencePersistsItAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training-evidences/new');

        // Realistic data shape: an evidence not linked to any planned action, questionnaire done.
        $client->submitForm('Guardar', [
            'training_evidence[personName]' => 'Persona de ejemplo',
            'training_evidence[trainingDescription]' => 'Sensibilización ambiental ISO 14001',
            'training_evidence[trainingDate]' => '2025-09-03',
            'training_evidence[questionnaireCompleted]' => true,
        ]);

        self::assertResponseRedirects('/training-evidences');

        $all = static::getContainer()->get(TrainingEvidenceRepository::class)->findAllOrdered();
        self::assertCount(1, $all);
        $evidence = $all[0];
        self::assertSame('Persona de ejemplo', $evidence->getPersonName());
        self::assertSame('Sensibilización ambiental ISO 14001', $evidence->getTrainingDescription());
        self::assertTrue($evidence->isQuestionnaireCompleted());
        // Unlinked by default.
        self::assertNull($evidence->getTrainingAction());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'training_evidence.created'])
        );

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Persona de ejemplo');
    }

    public function testSubmittingEvidenceLinkedToATrainingActionStoresTheLink(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $action = (new TrainingAction())
            ->setPlanYear(2025)
            ->setDescription('Sensibilización ambiental ISO 14001')
            ->setType(TrainingType::INTERNAL)
            ->setTargetAudience('Todo el profesorado')
            ->setObjectives('Difundir la política ambiental.')
            ->setPlannedDate(new \DateTimeImmutable('2025-09-03'))
            ->setMethodology('Charla en el claustro.');
        $em->persist($action);
        $em->flush();

        $client->request('GET', '/training-evidences/new');
        $client->submitForm('Guardar', [
            'training_evidence[personName]' => 'Persona de ejemplo',
            'training_evidence[trainingDescription]' => 'Sensibilización ambiental ISO 14001',
            'training_evidence[trainingDate]' => '2025-09-03',
            'training_evidence[trainingAction]' => (string) $action->getId(),
        ]);

        self::assertResponseRedirects('/training-evidences');

        $all = static::getContainer()->get(TrainingEvidenceRepository::class)->findAllOrdered();
        self::assertCount(1, $all);
        $evidence = $all[0];
        // Questionnaire left unchecked is stored as false.
        self::assertFalse($evidence->isQuestionnaireCompleted());
        $linkedAction = $evidence->getTrainingAction();
        self::assertNotNull($linkedAction);
        self::assertSame('Sensibilización ambiental ISO 14001', $linkedAction->getDescription());
    }

    public function testEditingEvidenceUpdatesItAndLogs(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $evidence = (new TrainingEvidence())
            ->setPersonName('Nombre original')
            ->setTrainingDescription('Formación inicial')
            ->setTrainingDate(new \DateTimeImmutable('2025-01-10'));
        $em->persist($evidence);
        $em->flush();
        $id = $evidence->getId();

        $client->request('GET', '/training-evidences/'.$id.'/edit');
        $client->submitForm('Guardar', [
            'training_evidence[personName]' => 'Nombre corregido',
            'training_evidence[trainingDescription]' => 'Formación inicial',
            'training_evidence[trainingDate]' => '2025-01-10',
        ]);

        self::assertResponseRedirects('/training-evidences');

        $updated = static::getContainer()->get(TrainingEvidenceRepository::class)->find($id);
        self::assertNotNull($updated);
        self::assertSame('Nombre corregido', $updated->getPersonName());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'training_evidence.updated'])
        );
    }

    public function testSubmittingInvalidEvidenceRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/training-evidences/new');

        // Blank required name: the form must be re-rendered with errors, not persisted.
        $client->submitForm('Guardar', [
            'training_evidence[personName]' => '',
            'training_evidence[trainingDescription]' => 'Sensibilización ambiental ISO 14001',
            'training_evidence[trainingDate]' => '2025-09-03',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
        self::assertCount(0, static::getContainer()->get(TrainingEvidenceRepository::class)->findAllOrdered());
    }

    public function testDeletingEvidenceRemovesItAndRedirects(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $evidence = (new TrainingEvidence())
            ->setPersonName('Persona a eliminar')
            ->setTrainingDescription('Formación de prueba')
            ->setTrainingDate(new \DateTimeImmutable('2026-01-15'));
        $em->persist($evidence);
        $em->flush();
        $id = $evidence->getId();

        $client->request('GET', '/training-evidences');
        $client->submitForm('Eliminar');

        self::assertResponseRedirects('/training-evidences');
        self::assertNull(static::getContainer()->get(TrainingEvidenceRepository::class)->find($id));

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'training_evidence.deleted'])
        );
    }
}
