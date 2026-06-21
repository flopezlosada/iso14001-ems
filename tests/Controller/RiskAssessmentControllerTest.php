<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ProcessArea;
use App\Entity\RiskOpportunity;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Enum\RiskCategory;
use App\Enum\RiskOpportunityType;
use App\Repository\RiskOpportunityRepository;
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
        $client->submitForm('Guardar', [
            'risk_assessment[exercise]' => '2025-2026',
            'risk_assessment[probability]' => '3',
            'risk_assessment[impact]' => '2',
            'risk_assessment[revisionNumber]' => '1',
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
}
