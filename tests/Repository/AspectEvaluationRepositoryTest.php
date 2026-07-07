<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AspectEvaluation;
use App\Entity\EnvironmentalAspect;
use App\Repository\AspectEvaluationRepository;
use App\Repository\EnvironmentalAspectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the queries feeding the aspects workflow status, over a real test database
 * (rolled back per test). They prove the DQL filters actually exclude what the mocked unit test
 * cannot see: inactive aspects and evaluations of another year.
 */
final class AspectEvaluationRepositoryTest extends KernelTestCase
{
    private AspectEvaluationRepository $evaluations;
    private EnvironmentalAspectRepository $aspects;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->evaluations = $container->get(AspectEvaluationRepository::class);
        $this->aspects = $container->get(EnvironmentalAspectRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Persists an aspect, optionally with one evaluation for a given year, and returns it.
     */
    private function persistAspect(string $name, bool $active, ?int $evaluationYear = null, bool $significant = false): EnvironmentalAspect
    {
        $aspect = (new EnvironmentalAspect())->setName($name)->setActive($active);
        $this->entityManager->persist($aspect);

        if (null !== $evaluationYear) {
            $evaluation = (new AspectEvaluation())
                ->setAspect($aspect)
                ->setYear($evaluationYear)
                ->setSignificant($significant);
            $aspect->addEvaluation($evaluation);
            $this->entityManager->persist($evaluation);
        }

        return $aspect;
    }

    public function testFindByYearForActiveAspectsExcludesInactiveAndOtherYears(): void
    {
        $this->persistAspect('Electricidad', true, 2026, true);
        $this->persistAspect('Agua', true, 2026, false);
        $this->persistAspect('Papel', true, 2025, true);           // otro año → fuera
        $this->persistAspect('Tóner retirado', false, 2026, true); // inactivo → fuera
        $this->persistAspect('Ruido', true);                       // activo sin evaluar → sin fila
        $this->entityManager->flush();

        $byName = [];
        foreach ($this->evaluations->findByYearForActiveAspects(2026) as $evaluation) {
            $byName[$evaluation->getAspect()->getName()] = $evaluation;
        }
        ksort($byName);

        self::assertSame(['Agua', 'Electricidad'], array_keys($byName));
        self::assertTrue($byName['Electricidad']->isSignificant());
        self::assertFalse($byName['Agua']->isSignificant());
    }

    public function testCountActiveIgnoresInactiveAspects(): void
    {
        $this->persistAspect('Electricidad', true, 2026, true);
        $this->persistAspect('Papel', true, 2025);
        $this->persistAspect('Ruido', true);
        $this->persistAspect('Tóner retirado', false, 2026); // inactivo → no cuenta
        $this->entityManager->flush();

        self::assertSame(3, $this->aspects->countActive());
    }
}
