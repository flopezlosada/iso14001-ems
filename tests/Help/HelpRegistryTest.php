<?php

declare(strict_types=1);

namespace App\Tests\Help;

use App\Help\HelpRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see HelpRegistry}: it must parse the topic YAML files, index them by slug and by
 * route, and fail loudly on content errors (missing field, duplicate slug or route) so a broken help
 * catalogue is caught by CI rather than served silently. Runs against a throwaway directory of
 * fixture files, so it has no database or kernel dependency.
 */
final class HelpRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/help-test-'.uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    private function write(string $filename, string $yaml): void
    {
        file_put_contents($this->dir.'/'.$filename, $yaml);
    }

    private function validTopic(string $slug, string $route): string
    {
        return <<<YAML
            slug: {$slug}
            title: Título {$slug}
            summary: Un resumen breve.
            routes: [{$route}]
            body: |
                <p>Cuerpo.</p>
            YAML;
    }

    public function testMissingDirectoryYieldsEmptyCatalogue(): void
    {
        $registry = new HelpRegistry($this->dir.'/does-not-exist');

        self::assertSame([], $registry->all());
        self::assertNull($registry->bySlug('anything'));
        self::assertNull($registry->byRoute('anything'));
    }

    public function testLoadsTopicAndResolvesBySlugAndRoute(): void
    {
        $this->write('aspectos.yaml', <<<'YAML'
            slug: aspectos-ambientales
            title: Aspectos ambientales
            summary: "  Resumen con espacios.  "
            routes: [aspect_index, aspect_show]
            legal:
                - label: "ISO 14001 · 6.1.2"
                  note: "Identificar aspectos."
                  url: "https://example.test/iso"
                - label: "Sin enlace ni nota"
            docs: [PG-06.01, RG-06.01.01]
            body: |
                <h2>Cómo funciona</h2>
            YAML);

        $registry = new HelpRegistry($this->dir);

        $bySlug = $registry->bySlug('aspectos-ambientales');
        self::assertNotNull($bySlug);
        self::assertSame('Aspectos ambientales', $bySlug->title);
        self::assertSame('Resumen con espacios.', $bySlug->summary, 'summary is trimmed');
        self::assertSame(['aspect_index', 'aspect_show'], $bySlug->routes);
        self::assertSame(['PG-06.01', 'RG-06.01.01'], $bySlug->docCodes);

        // Both routes resolve to the same topic.
        self::assertSame($bySlug, $registry->byRoute('aspect_index'));
        self::assertSame($bySlug, $registry->byRoute('aspect_show'));
        self::assertNull($registry->byRoute('unknown_route'));

        // Legal refs: the one without url keeps a null link, the one without note an empty string.
        self::assertCount(2, $bySlug->legal);
        self::assertSame('https://example.test/iso', $bySlug->legal[0]->url);
        self::assertSame('Identificar aspectos.', $bySlug->legal[0]->note);
        self::assertNull($bySlug->legal[1]->url);
        self::assertSame('', $bySlug->legal[1]->note);
    }

    public function testAllIsOrderedByTitle(): void
    {
        $this->write('b.yaml', $this->validTopic('zeta', 'route_z'));
        $this->write('a.yaml', $this->validTopic('alfa', 'route_a'));

        $titles = array_map(static fn ($t) => $t->title, (new HelpRegistry($this->dir))->all());

        self::assertSame(['Título alfa', 'Título zeta'], $titles);
    }

    public function testMissingRequiredFieldThrows(): void
    {
        $this->write('bad.yaml', <<<'YAML'
            slug: no-summary
            title: Sin resumen
            body: "x"
            YAML);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('summary');

        (new HelpRegistry($this->dir))->all();
    }

    public function testDuplicateSlugThrows(): void
    {
        $this->write('one.yaml', $this->validTopic('dup', 'route_one'));
        $this->write('two.yaml', $this->validTopic('dup', 'route_two'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate help slug');

        (new HelpRegistry($this->dir))->all();
    }

    public function testRouteClaimedByTwoTopicsThrows(): void
    {
        $this->write('one.yaml', $this->validTopic('slug-one', 'shared_route'));
        $this->write('two.yaml', $this->validTopic('slug-two', 'shared_route'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('shared_route');

        (new HelpRegistry($this->dir))->all();
    }
}
