<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Help\HelpRegistry;
use App\Help\HelpTopic;
use App\Twig\HelpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Unit tests for {@see HelpExtension::helpButton()}: it renders the "?" link for an explicit slug or
 * for the current screen's route, renders nothing when there is no topic, and escapes topic data
 * that lands in HTML attributes.
 */
final class HelpExtensionTest extends TestCase
{
    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn (string $name, array $params = []): string => '/ayuda/'.($params['slug'] ?? ''),
        );

        return $urls;
    }

    private function requestStackOnRoute(?string $route): RequestStack
    {
        $stack = new RequestStack();
        if (null !== $route) {
            $request = Request::create('/');
            $request->attributes->set('_route', $route);
            $stack->push($request);
        }

        return $stack;
    }

    public function testRendersButtonForExplicitSlug(): void
    {
        $topic = new HelpTopic('aspectos-ambientales', 'Aspectos ambientales', 'x', '<p>y</p>');
        $registry = $this->createMock(HelpRegistry::class);
        $registry->method('bySlug')->with('aspectos-ambientales')->willReturn($topic);

        $html = (new HelpExtension($registry, $this->requestStackOnRoute(null), $this->urls()))
            ->helpButton('aspectos-ambientales');

        self::assertStringContainsString('class="help-btn"', $html);
        self::assertStringContainsString('href="/ayuda/aspectos-ambientales"', $html);
        self::assertStringContainsString('data-help="aspectos-ambientales"', $html);
        self::assertStringContainsString('data-help-title="Aspectos ambientales"', $html);
        self::assertStringContainsString('aria-label="Ayuda: Aspectos ambientales"', $html);
    }

    public function testResolvesTopicFromCurrentRouteWhenNoSlugGiven(): void
    {
        $topic = new HelpTopic('aspectos-ambientales', 'Aspectos ambientales', 'x', '<p>y</p>');
        $registry = $this->createMock(HelpRegistry::class);
        $registry->method('byRoute')->with('aspect_index')->willReturn($topic);

        $html = (new HelpExtension($registry, $this->requestStackOnRoute('aspect_index'), $this->urls()))
            ->helpButton();

        self::assertStringContainsString('data-help="aspectos-ambientales"', $html);
    }

    public function testRendersNothingWhenNoTopicForRoute(): void
    {
        $registry = $this->createMock(HelpRegistry::class);
        $registry->method('byRoute')->willReturn(null);

        $html = (new HelpExtension($registry, $this->requestStackOnRoute('some_route'), $this->urls()))
            ->helpButton();

        self::assertSame('', $html);
    }

    public function testRendersNothingForUnknownSlug(): void
    {
        $registry = $this->createMock(HelpRegistry::class);
        $registry->method('bySlug')->willReturn(null);

        $html = (new HelpExtension($registry, $this->requestStackOnRoute(null), $this->urls()))
            ->helpButton('ghost');

        self::assertSame('', $html);
    }

    public function testEscapesTopicTitleInAttributes(): void
    {
        $topic = new HelpTopic('x', 'Café "verde" <b>', 's', '<p>b</p>');
        $registry = $this->createMock(HelpRegistry::class);
        $registry->method('bySlug')->willReturn($topic);

        $html = (new HelpExtension($registry, $this->requestStackOnRoute(null), $this->urls()))
            ->helpButton('x');

        self::assertStringNotContainsString('<b>', $html, 'raw markup from the title must not leak');
        self::assertStringContainsString('&quot;verde&quot;', $html);
    }
}
