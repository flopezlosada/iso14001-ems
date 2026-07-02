<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ConsumptionReading;
use App\Entity\EnvironmentalAspect;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
use App\Enum\AspectType;
use App\Enum\ConsumptionType;
use App\Enum\DirectAspectCategory;
use App\Enum\PermissionLevel;
use App\Repository\EnvironmentalAspectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional (FE + BE) smoke tests for aspect evaluations, nested under an aspect. Verifies the
 * significance is computed and persisted on save. Rolled back after each test by DAMA.
 */
final class AspectEvaluationControllerTest extends WebTestCase
{
    /**
     * @return array{0: KernelBrowser, 1: int} [client, aspectId]
     */
    private function scenario(DirectAspectCategory $category): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setCode('aspectos')->setName('Gestión de aspectos')->setLevel(Area::ASPECT, PermissionLevel::WRITE);
        $em->persist($role);

        $user = new User();
        $user->setFullName('Tester')->setEmail('aeval-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $aspect = (new EnvironmentalAspect())->setName('Electricidad')->setCategory($category);
        $em->persist($aspect);

        $em->flush();
        $client->loginUser($user);

        return [$client, $aspect->getId()];
    }

    public function testNewEvaluationFormRenders(): void
    {
        [$client, $aspectId] = $this->scenario(DirectAspectCategory::CONSUMPTION);
        $client->request('GET', '/aspects/'.$aspectId.'/evaluations/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select#aspect_evaluation_frequency');
        // The per-field contextual help must actually render on the real form (it goes through the
        // shared macro, which reads help_slug): guards against the field help becoming dead code.
        self::assertSelectorExists('.help-field-label a.help-btn[data-help="aspecto-frecuencia"]');
    }

    public function testSubmittingEvaluationComputesSignificance(): void
    {
        [$client, $aspectId] = $this->scenario(DirectAspectCategory::CONSUMPTION);
        $client->request('GET', '/aspects/'.$aspectId.'/evaluations/new');
        $client->submitForm('Guardar', [
            'aspect_evaluation[year]' => '2026',
            'aspect_evaluation[frequency]' => '6',
            'aspect_evaluation[intensity]' => '6',
            'aspect_evaluation[hazard]' => '6',
        ]);

        self::assertResponseRedirects('/aspects/'.$aspectId);

        $aspect = static::getContainer()->get(EnvironmentalAspectRepository::class)->find($aspectId);
        self::assertNotNull($aspect);
        $latest = $aspect->getLatestEvaluation();
        self::assertNotNull($latest);
        self::assertSame(18, $latest->getSignificanceScore());
        self::assertTrue($latest->isSignificant());
    }

    public function testEvaluationWithoutIntensityDefaultsToFour(): void
    {
        [$client, $aspectId] = $this->scenario(DirectAspectCategory::WASTE);
        $client->request('GET', '/aspects/'.$aspectId.'/evaluations/new');
        // Leave intensity empty -> counts as 4 (no prior-year data). 2 + 4 + 2 = 8 -> not significant.
        $client->submitForm('Guardar', [
            'aspect_evaluation[year]' => '2026',
            'aspect_evaluation[frequency]' => '2',
            'aspect_evaluation[hazard]' => '2',
        ]);

        $aspect = static::getContainer()->get(EnvironmentalAspectRepository::class)->find($aspectId);
        self::assertNotNull($aspect);
        $latest = $aspect->getLatestEvaluation();
        self::assertNotNull($latest);
        self::assertSame(8, $latest->getSignificanceScore());
        self::assertFalse($latest->isSignificant());
    }

    /**
     * @return array{0: KernelBrowser, 1: int} [client, aspectId]
     */
    private function scenarioForType(AspectType $type): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('aspectos')->setName('Gestión de aspectos')->setLevel(Area::ASPECT, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Tester')->setEmail('atype-tester@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);
        $aspect = (new EnvironmentalAspect())->setName('Aspecto')->setType($type);
        $em->persist($aspect);
        $em->flush();
        $client->loginUser($user);

        return [$client, $aspect->getId()];
    }

    public function testAbnormalEvaluationSumsItsCriteria(): void
    {
        [$client, $aspectId] = $this->scenarioForType(AspectType::ABNORMAL);
        $client->request('GET', '/aspects/'.$aspectId.'/evaluations/new');
        // The form must expose the abnormal criteria, not the direct ones.
        self::assertSelectorExists('select#aspect_evaluation_probability');

        $client->submitForm('Guardar', [
            'aspect_evaluation[year]' => '2026',
            'aspect_evaluation[probability]' => '6',
            'aspect_evaluation[control]' => '4',
            'aspect_evaluation[severity]' => '4',
        ]);

        $aspect = static::getContainer()->get(EnvironmentalAspectRepository::class)->find($aspectId);
        self::assertNotNull($aspect);
        $latest = $aspect->getLatestEvaluation();
        self::assertNotNull($latest);
        self::assertSame(14, $latest->getSignificanceScore());
        self::assertTrue($latest->isSignificant());
    }

    public function testIndirectEvaluationKeepsManualSignificance(): void
    {
        [$client, $aspectId] = $this->scenarioForType(AspectType::INDIRECT);
        $client->request('GET', '/aspects/'.$aspectId.'/evaluations/new');
        self::assertSelectorExists('select#aspect_evaluation_influence');

        $client->submitForm('Guardar', [
            'aspect_evaluation[year]' => '2026',
            'aspect_evaluation[influence]' => '3',
            'aspect_evaluation[significant]' => '1',
        ]);

        $aspect = static::getContainer()->get(EnvironmentalAspectRepository::class)->find($aspectId);
        self::assertNotNull($aspect);
        $latest = $aspect->getLatestEvaluation();
        self::assertNotNull($latest);
        self::assertSame(3, $latest->getSignificanceScore());
        self::assertTrue($latest->isSignificant());
    }

    public function testNewEvaluationPreFillsSuggestedIntensityForLinkedAspect(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $role = (new Role())->setCode('aspectos')->setName('Gestión de aspectos')->setLevel(Area::ASPECT, PermissionLevel::WRITE);
        $em->persist($role);
        $user = (new User())->setFullName('Tester')->setEmail('aeval-linked@example.test')->setActive(true)->addAssignedRole($role);
        $em->persist($user);

        $aspect = (new EnvironmentalAspect())
            ->setName('Electricidad')
            ->setCategory(DirectAspectCategory::CONSUMPTION)
            ->setLinkedConsumptionType(ConsumptionType::ELECTRICITY);
        $em->persist($aspect);

        // +30% this year vs last year over the same month → the suggestion must be surfaced.
        $year = (int) date('Y');
        foreach ([[$year - 1, '1000'], [$year, '1300']] as [$periodYear, $quantity]) {
            $reading = (new ConsumptionReading())
                ->setType(ConsumptionType::ELECTRICITY)
                ->setPeriodYear($periodYear)
                ->setPeriodMonth(1)
                ->setQuantity($quantity);
            $em->persist($reading);
        }
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/aspects/'.$aspect->getId().'/evaluations/new');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Intensidad sugerida', (string) $client->getResponse()->getContent());
    }
}
