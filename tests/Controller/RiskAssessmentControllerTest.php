<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ProcessArea;
use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\RiskCategory;
use App\Enum\RiskLevel;
use App\Enum\RiskOpportunityType;
use App\Repository\RiskOpportunityRepository;
use App\Util\SchoolYear;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test of the valuation flow: submitting a valuation computes its score and category
 * end-to-end (controller + {@see \App\Service\RiskScoreCalculator}). DAMA rolls back writes.
 */
final class RiskAssessmentControllerTest extends WebTestCase
{
    private RiskOpportunity $item;

    private function loggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('riesgos')->setName('Gestión de riesgos')->setLevel(Area::RISK_OPPORTUNITY, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('risk-assessment-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $area = (new ProcessArea())->setName('Dirección');
        $em->persist($area);

        $this->item = (new RiskOpportunity())
            ->setType(RiskOpportunityType::RISK)
            ->setDescription('Burocracia en la toma de decisiones')
            ->setProcessArea($area);
        $em->persist($this->item);

        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    public function testSubmittingValuationComputesScoreAndCategory(): void
    {
        $client = $this->loggedInClient();
        $itemId = $this->item->getId();

        $client->request('GET', '/risks/'.$itemId.'/assessments/new');
        // The "Curso" selector is preselected to the current school year, so it is not overridden here.
        $client->submitForm('Guardar', [
            'risk_assessment[probability]' => '3',
            'risk_assessment[impact]' => '2',
        ]);

        self::assertResponseRedirects('/risks/'.$itemId);

        // Reload the item and read its (single) assessment: 3 × 2 = 6 -> critical.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $item = static::getContainer()->get(RiskOpportunityRepository::class)->find($itemId);
        $assessment = $item->getAssessments()->first();

        self::assertNotFalse($assessment);
        self::assertSame(6, $assessment->getScore());
        self::assertSame(RiskCategory::CRITICAL, $assessment->getCategory());
    }

    public function testNewFormDoesNotOfferYearsAlreadyValued(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $current = SchoolYear::current(new \DateTimeImmutable('today'));
        $this->persistAssessment($em, $current);

        $crawler = $client->request('GET', '/risks/'.$this->item->getId().'/assessments/new');

        self::assertResponseIsSuccessful();
        $offered = $crawler->filter('#risk_assessment_exercise option')->each(static fn ($option): ?string => $option->attr('value'));
        // The already-valued current year is gone; the still-free next year is offered.
        self::assertNotContains($current, $offered);
        self::assertContains(SchoolYear::next($current), $offered);
        // Per-field contextual help renders on the valuation form (guards against a slug typo).
        self::assertSelectorExists('.help-field-label a.help-btn[data-help="riesgo-probabilidad"]');
    }

    public function testNewRedirectsWhenEveryAvailableYearIsAlreadyValued(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $current = SchoolYear::current(new \DateTimeImmutable('today'));
        $this->persistAssessment($em, SchoolYear::previous($current));
        $this->persistAssessment($em, $current);
        $this->persistAssessment($em, SchoolYear::next($current));

        $client->request('GET', '/risks/'.$this->item->getId().'/assessments/new');

        // Nothing left to add: bounce back to the item instead of rendering a choiceless form.
        self::assertResponseRedirects('/risks/'.$this->item->getId());
    }

    public function testDirectionApprovesValuation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedItem($em);
        $assessment = $this->persistAssessment($em, '2025-2026');
        // Dirección approves the F.08.0; the role also grants area read so the page renders.
        $this->loginWithRole($client, $em, 'direction', 'aprueba-direccion@example.test');

        $client->request('GET', '/risks/'.$this->item->getId());
        $client->submitForm('Aprobar');

        self::assertResponseRedirects('/risks/'.$this->item->getId());

        $em->clear();
        $reloaded = $em->find(RiskAssessment::class, $assessment->getId());
        self::assertNotNull($reloaded);
        $approvedBy = $reloaded->getApprovedBy();
        self::assertNotNull($approvedBy);
        self::assertSame('Aprobador', $approvedBy->getFullName());
        self::assertNotNull($reloaded->getApprovedAt());
    }

    public function testRsgmaCanApproveValuation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedItem($em);
        $assessment = $this->persistAssessment($em, '2025-2026');
        $this->loginWithRole($client, $em, 'ems_manager', 'aprueba-sga@example.test');

        $client->request('GET', '/risks/'.$this->item->getId());
        $client->submitForm('Aprobar');

        self::assertResponseRedirects('/risks/'.$this->item->getId());
        $em->clear();
        $reloaded = $em->find(RiskAssessment::class, $assessment->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getApprovedBy());
    }

    public function testNonApproverCannotApprove(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedItem($em);
        $assessment = $this->persistAssessment($em, '2025-2026');
        // 'quality' has write on the area (can edit) but is NOT an approver of valuations.
        $this->loginWithRole($client, $em, 'quality', 'no-aprueba@example.test');

        // The voter runs before the CSRF check, so the 403 here is the authorization gate.
        $client->request('POST', '/risks/'.$this->item->getId().'/assessments/'.$assessment->getId().'/approve');

        self::assertResponseStatusCodeSame(403);
    }

    public function testApprovedValuationShowsApproverAndHidesButton(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedItem($em);
        $approver = $this->persistUser($em, 'direction', 'ya-aprobado@example.test');
        $assessment = $this->persistAssessment($em, '2025-2026');
        $assessment->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable('2026-01-15'));
        $em->flush();
        $client->loginUser($approver);

        $crawler = $client->request('GET', '/risks/'.$this->item->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'Aprobador');
        // Already approved: no second approval is offered.
        self::assertCount(0, $crawler->filter('form[action$="/approve"]'));
    }

    public function testEditingApprovedValuationBumpsRevisionAndClearsApproval(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedItem($em);
        $approver = $this->persistUser($em, 'direction', 'rev-bump@example.test');
        $assessment = $this->persistAssessment($em, '2025-2026');
        $assessment->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable('2026-01-15'));
        $em->flush();
        $assessmentId = $assessment->getId();
        $client->loginUser($approver);

        $client->request('GET', '/risks/'.$this->item->getId().'/assessments/'.$assessmentId.'/edit');
        // A REAL change (impact 2 -> 1) of an approved valuation is a new revision: the number bumps
        // automatically (no manual field) and the previous approval is cleared. "Curso" is locked here.
        $client->submitForm('Guardar', [
            'risk_assessment[probability]' => '3',
            'risk_assessment[impact]' => '1',
        ]);

        self::assertResponseRedirects('/risks/'.$this->item->getId());
        $em->clear();
        $reloaded = $em->find(RiskAssessment::class, $assessmentId);
        self::assertNotNull($reloaded);
        self::assertSame(2, $reloaded->getRevisionNumber());
        self::assertNull($reloaded->getApprovedBy());
        self::assertNull($reloaded->getApprovedAt());
    }

    public function testSavingApprovedValuationUnchangedKeepsRevisionAndApproval(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedItem($em);
        $approver = $this->persistUser($em, 'direction', 'noop-save@example.test');
        $assessment = $this->persistAssessment($em, '2025-2026'); // probability 3, impact 2
        $assessment->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable('2026-01-15'));
        $em->flush();
        $assessmentId = $assessment->getId();
        $client->loginUser($approver);

        $client->request('GET', '/risks/'.$this->item->getId().'/assessments/'.$assessmentId.'/edit');
        // Re-saving with the SAME values is a no-op: it must not bump the revision nor drop approval.
        $client->submitForm('Guardar', [
            'risk_assessment[probability]' => '3',
            'risk_assessment[impact]' => '2',
        ]);

        self::assertResponseRedirects('/risks/'.$this->item->getId());
        $em->clear();
        $reloaded = $em->find(RiskAssessment::class, $assessmentId);
        self::assertNotNull($reloaded);
        self::assertSame(1, $reloaded->getRevisionNumber());
        self::assertNotNull($reloaded->getApprovedBy());
        self::assertNotNull($reloaded->getApprovedAt());
    }

    public function testEditingDraftValuationDoesNotBumpRevision(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedItem($em);
        $editor = $this->persistUser($em, 'direction', 'draft-edit@example.test');
        $assessment = $this->persistAssessment($em, '2025-2026'); // unapproved draft, revision 1
        $assessmentId = $assessment->getId();
        $client->loginUser($editor);

        $client->request('GET', '/risks/'.$this->item->getId().'/assessments/'.$assessmentId.'/edit');
        $client->submitForm('Guardar', [
            'risk_assessment[probability]' => '3',
            'risk_assessment[impact]' => '2',
        ]);

        self::assertResponseRedirects('/risks/'.$this->item->getId());
        $em->clear();
        $reloaded = $em->find(RiskAssessment::class, $assessmentId);
        self::assertNotNull($reloaded);
        // Still a draft being refined: the revision stays put (only edits to an approved one bump it).
        self::assertSame(1, $reloaded->getRevisionNumber());
        self::assertNull($reloaded->getApprovedBy());
    }

    /** Persists the item under test (a risk) into {@see self::$item}. */
    private function seedItem(EntityManagerInterface $em): void
    {
        $area = (new ProcessArea())->setName('Dirección');
        $em->persist($area);
        $this->item = (new RiskOpportunity())
            ->setType(RiskOpportunityType::RISK)
            ->setDescription('Burocracia en la toma de decisiones')
            ->setProcessArea($area);
        $em->persist($this->item);
        $em->flush();
    }

    /** Persists a draft (unapproved) valuation of {@see self::$item} for the given exercise. */
    private function persistAssessment(EntityManagerInterface $em, string $exercise): RiskAssessment
    {
        $assessment = (new RiskAssessment())
            ->setExercise($exercise)
            ->setProbability(RiskLevel::HIGH)
            ->setImpact(RiskLevel::MEDIUM);
        $this->item->addAssessment($assessment);
        $em->persist($assessment);
        $em->flush();

        return $assessment;
    }

    /** Persists an active user holding the role with the given code (with area write so pages render). */
    private function persistUser(EntityManagerInterface $em, string $roleCode, string $email): User
    {
        $role = (new Role())->setCode($roleCode)->setName('Rol '.$roleCode)->setLevel(Area::RISK_OPPORTUNITY, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Aprobador')->setEmail($email)->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function loginWithRole(KernelBrowser $client, EntityManagerInterface $em, string $roleCode, string $email): void
    {
        $client->loginUser($this->persistUser($em, $roleCode, $email));
    }
}
