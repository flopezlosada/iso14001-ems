<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview;

use App\Entity\ManagementReview;
use App\Entity\ManagementReviewSection;
use App\Enum\ReviewSectionKey;
use App\Service\ManagementReview\ManagementReviewPdfGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the official management review report (RG-09.03.01) renders to a real PDF through the
 * full stack (Twig template + dompdf), with Spanish content that exercises diacritics.
 */
final class ManagementReviewPdfGeneratorTest extends KernelTestCase
{
    public function testRendersReportAsPdf(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(ManagementReviewPdfGenerator::class);
        self::assertInstanceOf(ManagementReviewPdfGenerator::class, $generator);

        $review = (new ManagementReview())->setExercise('2025-2026');
        $review->addSection(
            (new ManagementReviewSection())
                ->setSectionKey(ReviewSectionKey::PREVIOUS_ACTIONS)
                ->setPosition(0)
                ->setContent('Las acciones de la revisión anterior están cerradas y verificadas.'),
        );
        // A section left empty (the realistic shape: the prefiller creates sections with null content
        // for keys without a provider) must render without error.
        $review->addSection(
            (new ManagementReviewSection())
                ->setSectionKey(ReviewSectionKey::CONTEXT_CHANGES)
                ->setPosition(1)
                ->setContent(null),
        );
        $review->addSection(
            (new ManagementReviewSection())
                ->setSectionKey(ReviewSectionKey::CONCLUSIONS)
                ->setPosition(13)
                ->setContent('El SGMA se considera adecuado, conveniente y eficaz para el curso.'),
        );

        $pdf = $generator->render($review);

        // A well-formed PDF starts with the magic bytes and is not a near-empty stub.
        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(800, \strlen($pdf));
    }
}
