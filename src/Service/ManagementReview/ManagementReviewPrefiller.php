<?php

declare(strict_types=1);

namespace App\Service\ManagementReview;

use App\Entity\ManagementReview;
use App\Entity\ManagementReviewSection;
use App\Enum\ReviewSectionKey;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Builds the fixed set of sections for a management review and seeds the ones that have a
 * {@see SectionSummaryProvider} with a frozen snapshot of the matching module's data.
 *
 * The snapshot is taken once, at generation time, and copied into the section's content (only when
 * the content is still empty, so an edited section is never overwritten). This is deliberately not
 * a live binding: the RG-09.03.01 is signed off, so its figures must not change afterwards.
 */
final class ManagementReviewPrefiller
{
    /**
     * @param iterable<SectionSummaryProvider> $providers all registered section summary providers
     */
    public function __construct(
        #[AutowireIterator('app.review_section_provider')]
        private readonly iterable $providers,
    ) {
    }

    /**
     * Ensures the review has every section (in enum order) and (re)generates the snapshot for each
     * section that has a provider, filling the content only when it is still empty.
     *
     * @param ManagementReview $review the review to prefill (typically just created)
     */
    public function prefill(ManagementReview $review): void
    {
        $providersByKey = $this->providersByKey();
        $position = 0;

        foreach (ReviewSectionKey::cases() as $key) {
            $section = $review->getSection($key) ?? new ManagementReviewSection();
            $section->setSectionKey($key);
            $section->setPosition($position++);

            $provider = $providersByKey[$key->value] ?? null;
            if (null !== $provider) {
                $summary = $provider->summarize($review->getExercise());
                $section->setGeneratedSnapshot('' === $summary ? null : $summary);

                if (null === $section->getContent() || '' === trim((string) $section->getContent())) {
                    $section->setContent('' === $summary ? null : $summary);
                }
            }

            $review->addSection($section);
        }
    }

    /**
     * Indexes the registered providers by their section key, so each section is filled at most once.
     *
     * @return array<string, SectionSummaryProvider> providers keyed by section key value
     */
    private function providersByKey(): array
    {
        $byKey = [];
        foreach ($this->providers as $provider) {
            $byKey[$provider->section()->value] = $provider;
        }

        return $byKey;
    }
}
