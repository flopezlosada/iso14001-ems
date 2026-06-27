<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ProcessArea;
use App\Entity\RiskAction;
use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\RiskLevel;
use App\Enum\RiskOpportunityType;
use App\Repository\AuditLogRepository;
use App\Repository\RiskAssessmentRepository;
use App\Repository\RiskOpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for the risk/opportunity catalogue. Routes require write access
 * to the risk-and-opportunity area. Database writes are rolled back after each test by DAMA.
 */
final class RiskOpportunityControllerTest extends WebTestCase
{
    private ProcessArea $area;

    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('riesgos')->setName('Gestión de riesgos')->setLevel(Area::RISK_OPPORTUNITY, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('risk-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $this->area = (new ProcessArea())->setName('Formación');
        $em->persist($this->area);

        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testIndexRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/risks');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Riesgos y oportunidades');
    }

    public function testNewFormRenders(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/risks/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#risk_opportunity_type');
    }

    public function testSubmittingValidItemPersistsItAndRedirectsToDetail(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/risks/new');
        $client->submitForm('Guardar', [
            'risk_opportunity[type]' => 'risk',
            'risk_opportunity[description]' => 'Falta de conocimientos ambientales',
            'risk_opportunity[processArea]' => (string) $this->area->getId(),
        ]);

        $item = static::getContainer()->get(RiskOpportunityRepository::class)->findOneBy([]);
        self::assertNotNull($item);
        self::assertResponseRedirects('/risks/'.$item->getId());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'riskopportunity.created'])
        );
    }

    public function testSubmittingWithoutDescriptionRedisplaysFormWithErrors(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/risks/new');
        $client->submitForm('Guardar', [
            'risk_opportunity[type]' => 'opportunity',
            'risk_opportunity[description]' => '',
            'risk_opportunity[processArea]' => (string) $this->area->getId(),
        ]);

        self::assertFalse($client->getResponse()->isRedirect());
        self::assertSelectorExists('.form-row ul li');
    }

    public function testCloneButtonHiddenWhenNoAssessmentsExist(): void
    {
        $client = $this->loggedInClient();
        $this->persistRisk('Sin valorar todavía'); // a risk with no valuations at all

        $client->request('GET', '/risks');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/risks/clone-assessments"] button');
    }

    public function testCloneCreatesDraftsForUnvaluedRisks(): void
    {
        $client = $this->loggedInClient();
        $this->valuedRisk('Falta de formación', '2024-2025', RiskLevel::MEDIUM, RiskLevel::HIGH, 'Plan A');
        $this->valuedRisk('Cambios normativos', '2024-2025', RiskLevel::LOW, RiskLevel::LOW, null);

        $client->request('GET', '/risks');
        $client->submitForm('Clonar valoraciones a 2025-2026');

        self::assertResponseRedirects('/risks');

        $assessments = static::getContainer()->get(RiskAssessmentRepository::class);
        $next = $assessments->findByExercise('2025-2026');
        self::assertCount(2, $next);
        // Every clone is an unapproved Rev. 01 with a recomputed score (probability × impact).
        foreach ($next as $assessment) {
            self::assertNull($assessment->getApprovedBy());
            self::assertSame(1, $assessment->getRevisionNumber());
            self::assertSame($assessment->getProbability()->value * $assessment->getImpact()->value, $assessment->getScore());
        }

        // The action plan is carried over as a fresh draft (efficacy review reset for the new course).
        $withPlan = array_values(array_filter($next, static fn (RiskAssessment $a): bool => 'Falta de formación' === $a->getRiskOpportunity()->getDescription()))[0];
        self::assertCount(1, $withPlan->getActions());
        $action = $withPlan->getActions()->first();
        self::assertSame('Plan A', $action->getDescription());
        self::assertNull($action->getEfficacy());
        self::assertNull($action->getEvaluatedAt());

        self::assertNotNull(
            static::getContainer()->get(AuditLogRepository::class)->findOneBy(['action' => 'riskassessment.cloned_from_previous'])
        );
    }

    public function testCloneSkipsRisksAlreadyValuedForTheNextCourse(): void
    {
        $client = $this->loggedInClient();
        // Risk A is already valued in both courses; risk B only in the source course.
        $riskA = $this->valuedRisk('Riesgo A', '2024-2025', RiskLevel::HIGH, RiskLevel::HIGH, null);
        $this->addAssessment($riskA, '2025-2026', RiskLevel::LOW, RiskLevel::LOW);
        $this->valuedRisk('Riesgo B', '2024-2025', RiskLevel::MEDIUM, RiskLevel::MEDIUM, null);

        $client->request('GET', '/risks');
        $client->submitForm('Clonar valoraciones a 2025-2026');

        $next = static::getContainer()->get(RiskAssessmentRepository::class)->findByExercise('2025-2026');
        // Risk A keeps its single (pre-existing) 2025-2026 valuation; only risk B gets a fresh one.
        self::assertCount(2, $next);
        $aNext = array_filter($next, static fn (RiskAssessment $a): bool => 'Riesgo A' === $a->getRiskOpportunity()->getDescription());
        self::assertCount(1, $aNext, 'risk A is not duplicated for the next course');
    }

    private function persistRisk(string $description): RiskOpportunity
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $risk = (new RiskOpportunity())
            ->setType(RiskOpportunityType::RISK)
            ->setDescription($description)
            ->setProcessArea($this->area);
        $em->persist($risk);
        $em->flush();

        return $risk;
    }

    private function valuedRisk(string $description, string $exercise, RiskLevel $probability, RiskLevel $impact, ?string $actionDescription): RiskOpportunity
    {
        $risk = $this->persistRisk($description);
        $this->addAssessment($risk, $exercise, $probability, $impact, $actionDescription);

        return $risk;
    }

    private function addAssessment(RiskOpportunity $risk, string $exercise, RiskLevel $probability, RiskLevel $impact, ?string $actionDescription = null): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $assessment = (new RiskAssessment())
            ->setExercise($exercise)
            ->setProbability($probability)
            ->setImpact($impact)
            ->setScore($probability->value * $impact->value);
        $risk->addAssessment($assessment);

        if (null !== $actionDescription) {
            $assessment->addAction((new RiskAction())->setDescription($actionDescription)->setResponsible('RESPO SGMA'));
        }

        $em->persist($assessment);
        $em->flush();
    }
}
