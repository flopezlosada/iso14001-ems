<?php

declare(strict_types=1);

namespace App\Help;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads the contextual-help catalogue from the YAML files under the project's `help/` directory and
 * exposes it as {@see HelpTopic} objects, looked up by slug or by the screen's route name.
 *
 * Design choices:
 *  - Content lives in versioned files, not the database: the help is quasi-static, precise (legal
 *    wording) and reviewed by PR. There is no editor UI yet on purpose (YAGNI).
 *  - The registry has NO database dependency, so it can be unit-tested against a fixtures directory.
 *    Resolving an SGA document code to its actual page is the controller's job, not the registry's.
 *  - Files are parsed once per request and memoised. Content errors (missing field, duplicate slug
 *    or route) throw on load rather than serving broken help silently — they are caught by tests/CI.
 */
class HelpRegistry
{
    /**
     * Memoised topics indexed by slug, or null until the directory has been read.
     *
     * @var array<string, HelpTopic>|null
     */
    private ?array $bySlug = null;

    /**
     * Memoised map of route name → slug, built lazily alongside {@see $bySlug}.
     *
     * @var array<string, string>|null
     */
    private ?array $slugByRoute = null;

    /**
     * @param string $helpDir absolute path to the directory holding the topic YAML files
     */
    public function __construct(private readonly string $helpDir)
    {
    }

    /**
     * Every topic, ordered by title, for the help index page.
     *
     * @return list<HelpTopic>
     */
    public function all(): array
    {
        $topics = array_values($this->load());
        usort($topics, static fn (HelpTopic $a, HelpTopic $b): int => $a->title <=> $b->title);

        return $topics;
    }

    /**
     * The topic with the given slug, or null if there is none.
     */
    public function bySlug(string $slug): ?HelpTopic
    {
        return $this->load()[$slug] ?? null;
    }

    /**
     * The topic that provides help for the given Symfony route name, or null if no topic claims it.
     */
    public function byRoute(string $route): ?HelpTopic
    {
        $this->load();
        $slug = ($this->slugByRoute ?? [])[$route] ?? null;

        return null !== $slug ? $this->bySlug($slug) : null;
    }

    /**
     * Reads and parses every `*.yaml` file in the help directory once, building the slug and route
     * indexes. A missing directory yields an empty catalogue; a malformed file throws.
     *
     * @return array<string, HelpTopic> topics indexed by slug
     */
    private function load(): array
    {
        if (null !== $this->bySlug) {
            return $this->bySlug;
        }

        $this->bySlug = [];
        $this->slugByRoute = [];

        foreach ($this->topicFiles() as $file) {
            $topic = $this->parse($file);

            if (isset($this->bySlug[$topic->slug])) {
                throw new \RuntimeException(sprintf('Duplicate help slug "%s" (in %s).', $topic->slug, basename($file)));
            }
            $this->bySlug[$topic->slug] = $topic;

            foreach ($topic->routes as $route) {
                if (isset($this->slugByRoute[$route])) {
                    throw new \RuntimeException(sprintf('Route "%s" is claimed by two help topics ("%s" and "%s").', $route, $this->slugByRoute[$route], $topic->slug));
                }
                $this->slugByRoute[$route] = $topic->slug;
            }
        }

        return $this->bySlug;
    }

    /**
     * @return list<string> absolute paths of the topic YAML files, empty if the directory is absent
     */
    private function topicFiles(): array
    {
        if (!is_dir($this->helpDir)) {
            return [];
        }

        return glob($this->helpDir.'/*.yaml') ?: [];
    }

    /**
     * Parses one topic file into a {@see HelpTopic}, validating the required fields.
     */
    private function parse(string $file): HelpTopic
    {
        /** @var array<string, mixed> $data */
        $data = Yaml::parseFile($file);

        return new HelpTopic(
            slug: $this->requireString($data, 'slug', $file),
            title: $this->requireString($data, 'title', $file),
            summary: trim($this->requireString($data, 'summary', $file)),
            bodyHtml: trim($this->requireString($data, 'body', $file)),
            routes: $this->stringList($data['routes'] ?? []),
            legal: $this->legalRefs($data['legal'] ?? []),
            docCodes: $this->stringList($data['docs'] ?? []),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key, string $file): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            throw new \RuntimeException(sprintf('Help topic %s is missing a non-empty "%s".', basename($file), $key));
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $v): string => is_string($v) ? $v : '', $value),
            static fn (string $v): bool => '' !== $v,
        ));
    }

    /**
     * @param mixed $value
     *
     * @return list<HelpLegalReference>
     */
    private function legalRefs(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $refs = [];
        foreach ($value as $entry) {
            if (!is_array($entry) || !is_string($entry['label'] ?? null)) {
                continue;
            }
            $url = $entry['url'] ?? null;
            $refs[] = new HelpLegalReference(
                label: $entry['label'],
                note: is_string($entry['note'] ?? null) ? trim($entry['note']) : '',
                url: is_string($url) && '' !== $url ? $url : null,
            );
        }

        return $refs;
    }
}
