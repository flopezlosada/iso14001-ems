<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ProcessArea;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use App\Repository\AuditLogRepository;
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
}
