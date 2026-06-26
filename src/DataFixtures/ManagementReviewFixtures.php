<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ManagementReview;
use App\Entity\User;
use App\Service\ManagementReview\ManagementReviewPrefiller;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * A sample management review (PG-09.03.00 / RG-09.03.01) for the current course, pre-filled from
 * the other modules' seeded data so the auto-aggregation is visible end to end. Demo only.
 *
 * Depends on the modules whose data the input sections summarise, so their seed is flushed before
 * the prefiller queries it.
 */
final class ManagementReviewFixtures extends AbstractDemoFixture implements DependentFixtureInterface
{
    private const string EXERCISE = '2025-2026';

    public function __construct(private readonly ManagementReviewPrefiller $prefiller)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $review = new ManagementReview();
        $review->setExercise(self::EXERCISE)
            ->setMeetingDate(new \DateTimeImmutable('2026-06-15'))
            ->addParticipant($this->getReference(UserFixtures::ref('direccion'), User::class))
            ->addParticipant($this->getReference(UserFixtures::ref('sga'), User::class));

        // Seed the input sections with a frozen snapshot of the other modules' data.
        $this->prefiller->prefill($review);

        $manager->persist($review);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            RiskOpportunityFixtures::class,
            ObjectiveFixtures::class,
            EnvironmentalAspectFixtures::class,
            NonConformityFixtures::class,
            IndicatorFixtures::class,
            LegalRequirementFixtures::class,
        ];
    }
}
