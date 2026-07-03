<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\NonConformity;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\Efficacy;
use App\Enum\NonConformityOrigin;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
use App\Repository\CorrectiveActionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the corrective action UI, nested under a non-conformity.
 * Database writes are rolled back after each test by DAMA DoctrineTestBundle.
 */
final class CorrectiveActionControllerTest extends WebTestCase
{
    /**
     * Creates a logged-in client (with non-conformity write access) and seeds one open
     * non-conformity to attach actions to.
     *
     * @return array{0: KernelBrowser, 1: int, 2: int} [client, nonConformityId, userId]
     */
    private function scenario(): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('nc')->setName('Gestión de no conformidades')->setLevel(Area::NONCONFORMITY, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester NC')->setEmail('ca-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $nc = new NonConformity();
        $nc->setOrigin(NonConformityOrigin::EXTERNAL_AUDIT)
            ->setYear(2026)
            ->setSequence(1)
            ->setReference('NC.AE.2026.01')
            ->setDescription('Incumplimiento detectado en auditoría externa.');
        $em->persist($nc);

        $em->flush();
        $client->loginUser($user);

        return [$client, $nc->getId(), $user->getId()];
    }

    public function testShowRendersWithActionsSection(): void
    {
        [$client, $ncId] = $this->scenario();
        $client->request('GET', '/non-conformities/'.$ncId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'NC.AE.2026.01');
        self::assertSelectorTextContains('body', 'Acciones correctivas');
    }

    public function testNewActionFormRenders(): void
    {
        [$client, $ncId] = $this->scenario();
        $client->request('GET', '/non-conformities/'.$ncId.'/actions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('textarea#corrective_action_description');
    }

    public function testSubmittingValidActionAssignsSequenceAndRedirects(): void
    {
        [$client, $ncId] = $this->scenario();
        $client->request('GET', '/non-conformities/'.$ncId.'/actions/new');
        $client->submitForm('Guardar', [
            'corrective_action[description]' => 'Se contrata gestor de residuos peligrosos.',
        ]);

        self::assertResponseRedirects('/non-conformities/'.$ncId);

        $action = static::getContainer()->get(CorrectiveActionRepository::class)->findOneBy([]);
        self::assertNotNull($action);
        self::assertSame('AC.01', $action->getReference());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'correctiveaction.created'])
        );
    }

    public function testSequenceIncrementsWithinTheNonConformity(): void
    {
        [$client, $ncId] = $this->scenario();

        foreach (['Primera acción', 'Segunda acción'] as $description) {
            $client->request('GET', '/non-conformities/'.$ncId.'/actions/new');
            $client->submitForm('Guardar', ['corrective_action[description]' => $description]);
        }

        $references = array_map(
            static fn ($a): string => $a->getReference(),
            static::getContainer()->get(CorrectiveActionRepository::class)->findBy([], ['sequence' => 'ASC']),
        );

        self::assertSame(['AC.01', 'AC.02'], $references);
    }

    public function testAuthorizeActionViaCta(): void
    {
        [$client, $ncId] = $this->scenario();
        // La autorización ya no se edita en el formulario: se registra con el CTA 'Autorizar' de la
        // ficha, que estampa al usuario actual y la fecha.
        $client->request('GET', '/non-conformities/'.$ncId.'/actions/new');
        $client->submitForm('Guardar', [
            'corrective_action[description]' => 'Acción que requiere autorización de Dirección.',
            'corrective_action[requiresDirectionAuthorization]' => '1',
        ]);

        $client->request('GET', '/non-conformities/'.$ncId);
        $client->submitForm('Autorizar');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $action = static::getContainer()->get(CorrectiveActionRepository::class)->findOneBy([]);
        self::assertNotNull($action);
        self::assertNotNull($action->getAuthorizedBy());
        self::assertNotNull($action->getAuthorizedAt());
        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'correctiveaction.authorized'])
        );
    }

    public function testEvaluateEfficacyViaCta(): void
    {
        [$client, $ncId] = $this->scenario();
        // La eficacia ya no se edita en el formulario: se registra con el CTA 'Eficaz' / 'No eficaz'.
        $client->request('GET', '/non-conformities/'.$ncId.'/actions/new');
        $client->submitForm('Guardar', ['corrective_action[description]' => 'Acción a evaluar.']);

        $client->request('GET', '/non-conformities/'.$ncId);
        $client->submitForm('Eficaz');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $action = static::getContainer()->get(CorrectiveActionRepository::class)->findOneBy([]);
        self::assertNotNull($action);
        self::assertSame(Efficacy::OK, $action->getEfficacy());
        self::assertTrue($action->isReviewed());
        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'correctiveaction.efficacy_evaluated'])
        );
    }

    public function testSubmittingWithoutDescriptionRedisplaysFormWithErrors(): void
    {
        [$client, $ncId] = $this->scenario();
        $client->request('GET', '/non-conformities/'.$ncId.'/actions/new');
        $client->submitForm('Guardar', [
            'corrective_action[description]' => '',
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }
}
