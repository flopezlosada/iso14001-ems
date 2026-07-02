<?php

declare(strict_types=1);

namespace App\Help;

/**
 * A single legal/normative reference attached to a help topic: a plain-language summary of what a
 * clause requires, plus a link to the authoritative source (the "real law": ISO, BOE, EUR-Lex…).
 *
 * Immutable value object, built from a topic's YAML `legal:` list by {@see HelpRegistry}.
 */
final readonly class HelpLegalReference
{
    /**
     * @param string      $label short citation shown to the user, e.g. "ISO 14001:2015 · 6.1.2"
     * @param string      $note  plain-language summary of what the clause requires
     * @param string|null $url   link to the official source, or null if there is no public link
     */
    public function __construct(
        public string $label,
        public string $note,
        public ?string $url = null,
    ) {
    }
}
