<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview;

use App\Entity\ManagementReview;
use App\Enum\ReviewSectionKey;
use App\Service\ManagementReview\ManagementReviewPrefiller;
use App\Service\ManagementReview\SectionSummaryProvider;
use PHPUnit\Framework\TestCase;

final class ManagementReviewPrefillerTest extends TestCase
{
    /**
     * A provider stub that fills one fixed section with a deterministic summary.
     */
    private function providerFor(ReviewSectionKey $key): SectionSummaryProvider
    {
        return new class($key) implements SectionSummaryProvider {
            public function __construct(private readonly ReviewSectionKey $key)
            {
            }

            public function section(): ReviewSectionKey
            {
                return $this->key;
            }

            public function summarize(string $exercise): string
            {
                return 'RESUMEN '.$exercise;
            }
        };
    }

    public function testPrefillCreatesEverySectionInOrder(): void
    {
        $prefiller = new ManagementReviewPrefiller([]);
        $review = (new ManagementReview())->setExercise('2025-2026');

        $prefiller->prefill($review);

        self::assertCount(\count(ReviewSectionKey::cases()), $review->getSections());
        $position = 0;
        foreach ($review->getSections() as $section) {
            self::assertSame($position, $section->getPosition());
            ++$position;
        }
    }

    public function testSectionWithProviderIsSeededAndOthersAreEmpty(): void
    {
        $prefiller = new ManagementReviewPrefiller([$this->providerFor(ReviewSectionKey::OBJECTIVES)]);
        $review = (new ManagementReview())->setExercise('2025-2026');

        $prefiller->prefill($review);

        $objectives = $review->getSection(ReviewSectionKey::OBJECTIVES);
        self::assertNotNull($objectives);
        self::assertSame('RESUMEN 2025-2026', $objectives->getContent());
        self::assertSame('RESUMEN 2025-2026', $objectives->getGeneratedSnapshot());

        $conclusions = $review->getSection(ReviewSectionKey::CONCLUSIONS);
        self::assertNotNull($conclusions);
        self::assertNull($conclusions->getContent());
        self::assertNull($conclusions->getGeneratedSnapshot());
    }

    public function testRegeneratingNeverOverwritesEditedContent(): void
    {
        $prefiller = new ManagementReviewPrefiller([$this->providerFor(ReviewSectionKey::OBJECTIVES)]);
        $review = (new ManagementReview())->setExercise('2025-2026');

        $prefiller->prefill($review);
        $review->getSection(ReviewSectionKey::OBJECTIVES)?->setContent('editado a mano');

        $prefiller->prefill($review);

        $objectives = $review->getSection(ReviewSectionKey::OBJECTIVES);
        self::assertNotNull($objectives);
        self::assertSame('editado a mano', $objectives->getContent());
        self::assertSame('RESUMEN 2025-2026', $objectives->getGeneratedSnapshot());
        self::assertCount(\count(ReviewSectionKey::cases()), $review->getSections());
    }
}
