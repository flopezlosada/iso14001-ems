<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\EnvironmentalAspect;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Area;
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
}
