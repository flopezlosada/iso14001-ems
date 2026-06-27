<?php

declare(strict_types=1);

namespace App\Service\ManagementReview\Provider;

use App\Entity\DafoAnalysis;
use App\Enum\ReviewSectionKey;
use App\Repository\DafoAnalysisRepository;
use App\Service\ManagementReview\SectionSummaryProvider;

/**
 * Summarises the SWOT (DAFO) analysis of the centre's environmental context for the course (F.06.0),
 * for the "changes in external and internal issues" section of the management review.
 *
 * The DAFO is a 2x2 matrix of free-text quadrants, each of which may be empty for a given course;
 * only the filled quadrants are reported, so an analysis with, say, no threats recorded does not
 * print an empty heading.
 */
final class ContextSummaryProvider implements SectionSummaryProvider
{
    public function __construct(
        private readonly DafoAnalysisRepository $dafo,
    ) {
    }

    public function section(): ReviewSectionKey
    {
        return ReviewSectionKey::CONTEXT_CHANGES;
    }

    public function summarize(string $exercise): string
    {
        $analysis = $this->analysisForExercise($exercise);
        if (null === $analysis) {
            return '';
        }

        $quadrants = [
            'Debilidades' => $analysis->getWeaknesses(),
            'Amenazas' => $analysis->getThreats(),
            'Fortalezas' => $analysis->getStrengths(),
            'Oportunidades' => $analysis->getOpportunities(),
        ];

        $blocks = [];
        foreach ($quadrants as $label => $content) {
            if (null === $content || '' === trim($content)) {
                continue;
            }
            $blocks[] = $label.":\n".trim($content);
        }

        if ([] === $blocks) {
            return '';
        }

        $header = sprintf('Análisis del contexto (DAFO) del curso %s:', $analysis->getSchoolYear());

        return implode("\n\n", [$header, ...$blocks]);
    }

    /**
     * The DAFO analysis recorded for the requested course, if any.
     *
     * @param string $exercise the school year, e.g. "2025-2026"
     *
     * @return DafoAnalysis|null the matching analysis, or null
     */
    private function analysisForExercise(string $exercise): ?DafoAnalysis
    {
        // Normalise the incoming course separator (the public contract may be called with a slash
        // "2025/2026"); the persisted schoolYear is always hyphenated, enforced by the entity's
        // @Assert\Regex, so only the argument needs normalising. A single query (no per-item
        // lookups), so there is no N+1.
        $target = str_replace('/', '-', $exercise);
        foreach ($this->dafo->findAllOrdered() as $analysis) {
            if ($analysis->getSchoolYear() === $target) {
                return $analysis;
            }
        }

        return null;
    }
}
