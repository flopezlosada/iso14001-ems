<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Indicator;
use App\Entity\IndicatorMeasurement;
use App\Entity\Objective;
use App\Entity\Settings;
use App\Enum\NonConformityOrigin;
use App\Enum\ObjectiveStatus;
use App\Enum\SgmaProcess;
use App\Repository\NonConformityRepository;
use App\Service\AutomaticNonConformityGenerator;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test of the auto-non-conformity engine: it opens non-conformities only for the
 * enabled rules, only for the qualifying sources (breached measurements / unmet objectives), and is
 * idempotent (running it again opens nothing new). DAMA rolls back writes between tests.
 */
final class AutomaticNonConformityGeneratorTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function saveSettings(bool $indicators, bool $objectives): void
    {
        $settings = (new Settings())
            ->setAutoNcFromBreachedIndicators($indicators)
            ->setAutoNcFromUnmetObjectives($objectives);
        $this->em->persist($settings);
        $this->em->flush();
        // The provider caches per request; drop it so it reloads the row just saved.
        self::getContainer()->get(SettingsProvider::class)->invalidate();
    }

    private function indicatorWithMeasurement(string $name, string $value, bool $breached): void
    {
        $indicator = (new Indicator())->setName($name)->setProcess(SgmaProcess::PERFORMANCE_EVALUATION)->setReferenceValue('0');
        $this->em->persist($indicator);

        $measurement = (new IndicatorMeasurement())
            ->setIndicator($indicator)
            ->setYear(2026)
            ->setMonth(3)
            ->setValue($value)
            ->setBreached($breached);
        $this->em->persist($measurement);
    }

    private function objective(string $reference, ObjectiveStatus $status, string $schoolYear = '2025-2026'): void
    {
        $repo = $this->em->getRepository(Objective::class);
        $objective = (new Objective())
            ->setReference($reference)
            ->setSequence($repo->nextSequence())
            ->setSchoolYear($schoolYear)
            ->setDescription('Reducir el consumo de agua un 5%')
            ->setStatus($status);
        $this->em->persist($objective);
    }

    private function generator(): AutomaticNonConformityGenerator
    {
        return self::getContainer()->get(AutomaticNonConformityGenerator::class);
    }

    private function nonConformities(): NonConformityRepository
    {
        return self::getContainer()->get(NonConformityRepository::class);
    }

    public function testOpensNonConformitiesOnlyForQualifyingSourcesOfEnabledRules(): void
    {
        $this->saveSettings(true, true);
        $this->indicatorWithMeasurement('Consumo de agua', '120', true);   // qualifies
        $this->indicatorWithMeasurement('Consumo de luz', '80', false);    // not breached -> ignored
        $this->objective('OBJ-01', ObjectiveStatus::NOT_ACHIEVED);          // qualifies
        $this->objective('OBJ-02', ObjectiveStatus::ACHIEVED);             // achieved -> ignored
        $this->em->flush();

        $summary = $this->generator()->generate(new \DateTimeImmutable('2026-06-27'));

        self::assertSame(2, $summary['candidates']);
        self::assertSame(2, $summary['created']);

        $created = array_filter(
            $this->nonConformities()->findAllOrdered(),
            static fn ($nc): bool => null !== $nc->getAutoSourceKey(),
        );
        self::assertCount(2, $created);
        $keys = array_map(static fn ($nc): string => (string) $nc->getAutoSourceKey(), $created);
        self::assertContains('objective:'.$this->objectiveId('OBJ-01'), $keys);
        self::assertCount(
            1,
            array_filter($keys, static fn (string $k): bool => str_starts_with($k, 'indicator_measurement:')),
            'the breached measurement (and only it) opened a non-conformity',
        );
        foreach ($created as $nc) {
            self::assertSame(NonConformityOrigin::INTERNAL, $nc->getOrigin());
            self::assertNotSame('', $nc->getReference());
        }
    }

    public function testUnmetObjectivesOfOtherCoursesAreIgnored(): void
    {
        // Only the current course (2025-2026 for a June 2026 run) is evaluated: an unmet objective of
        // a closed course must not reopen a non-conformity (PG-06.04).
        $this->saveSettings(false, true);
        $this->objective('OBJ-10', ObjectiveStatus::NOT_ACHIEVED, '2024-2025'); // closed course -> ignored
        $this->objective('OBJ-11', ObjectiveStatus::NOT_ACHIEVED, '2025-2026'); // current course -> qualifies
        $this->em->flush();

        $summary = $this->generator()->generate(new \DateTimeImmutable('2026-06-27'));

        self::assertSame(1, $summary['candidates']);
        self::assertSame(1, $summary['created']);
    }

    public function testIsIdempotent(): void
    {
        $this->saveSettings(true, false);
        $this->indicatorWithMeasurement('Consumo de agua', '120', true);
        $this->em->flush();

        $first = $this->generator()->generate(new \DateTimeImmutable('2026-06-27'));
        $second = $this->generator()->generate(new \DateTimeImmutable('2026-06-28'));

        self::assertSame(1, $first['created']);
        self::assertSame(1, $second['candidates'], 'the source still qualifies');
        self::assertSame(0, $second['created'], 'but its non-conformity already exists');
    }

    public function testDisabledRulesOpenNothing(): void
    {
        $this->saveSettings(false, false);
        $this->indicatorWithMeasurement('Consumo de agua', '120', true);
        $this->objective('OBJ-01', ObjectiveStatus::NOT_ACHIEVED);
        $this->em->flush();

        $summary = $this->generator()->generate(new \DateTimeImmutable('2026-06-27'));

        self::assertSame(0, $summary['candidates']);
        self::assertSame(0, $summary['created']);
    }

    private function objectiveId(string $reference): int
    {
        return (int) $this->em->getRepository(Objective::class)->findOneBy(['reference' => $reference])->getId();
    }
}
