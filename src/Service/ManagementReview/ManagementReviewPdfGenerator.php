<?php

declare(strict_types=1);

namespace App\Service\ManagementReview;

use App\Entity\ManagementReview;
use App\Entity\ManagementReviewSection;
use App\Enum\ReviewSectionGroup;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Renders the official management review report (RG-09.03.01, ISO 14001:2015 §9.3) as a PDF: cover
 * (organisation, course, sign-off), the meeting data, the thirteen input sections (§9.3.2) and the
 * six output sections (§9.3.3), with a per-page footer carrying the register code and course.
 *
 * This is the auditable artefact of the review: it is sealed at approval, its bytes hashed and the
 * hash stored on the {@see ManagementReview}, so the report can be verified without circularity (the
 * PDF deliberately does NOT print its own integrity hash).
 *
 * Mirrors {@see \App\Service\Document\DocumentPdfGenerator}: dompdf (pure PHP, no external binary,
 * few files) suits the shared hosting whose hard limit is inodes, not disk.
 */
final class ManagementReviewPdfGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $organizationName,
    ) {
    }

    /**
     * Renders a management review to PDF bytes, splitting its sections into the input and output
     * halves of the report.
     *
     * @param ManagementReview $review the review to render
     *
     * @return string the raw PDF bytes
     */
    public function render(ManagementReview $review): string
    {
        $sections = $review->getSections()->toArray();
        $inputs = array_filter(
            $sections,
            static fn (ManagementReviewSection $s): bool => ReviewSectionGroup::INPUT === $s->getSectionKey()->group(),
        );
        $outputs = array_filter(
            $sections,
            static fn (ManagementReviewSection $s): bool => ReviewSectionGroup::OUTPUT === $s->getSectionKey()->group(),
        );

        $html = $this->twig->render('management_review/pdf/report.html.twig', [
            'organizationName' => $this->organizationName,
            'review' => $review,
            'inputs' => $inputs,
            'outputs' => $outputs,
        ]);

        $options = new Options();
        // DejaVu Sans ships with dompdf and covers Spanish diacritics without extra font files.
        $options->set('defaultFont', 'DejaVu Sans');
        // The report references no remote assets; keep it off to avoid SSRF and slow renders.
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
