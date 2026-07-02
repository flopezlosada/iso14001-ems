<?php

declare(strict_types=1);

namespace App\Help;

/**
 * A unit of contextual help: the explanation of one module, screen or concept of the application.
 *
 * Each topic is authored as a YAML file under the project's `help/` directory and loaded by
 * {@see HelpRegistry}. It feeds two surfaces:
 *  - the micro-help popover (the "?" button on a screen): {@see $title} + {@see $summary} + a link;
 *  - the full help page at /ayuda/{slug}: {@see $bodyHtml} plus the legal references and SGA docs.
 *
 * Immutable value object; it holds only content, never rendering or persistence logic.
 */
final readonly class HelpTopic
{
    /**
     * @param string                    $slug      URL-safe identifier, unique across topics
     * @param string                    $title     human-facing heading (Spanish)
     * @param string                    $summary   short plain-text blurb for the popover
     * @param string                    $bodyHtml  trusted HTML for the full page (authored in-repo)
     * @param list<string>             $routes    Symfony route names this topic gives help for
     * @param list<HelpLegalReference> $legal     legal/normative references
     * @param list<string>             $docCodes  ISO codes of related SGA documents (e.g. PG-06.01)
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public string $bodyHtml,
        public array $routes = [],
        public array $legal = [],
        public array $docCodes = [],
    ) {
    }
}
