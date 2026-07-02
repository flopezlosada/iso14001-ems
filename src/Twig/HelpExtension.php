<?php

declare(strict_types=1);

namespace App\Twig;

use App\Help\HelpRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Renders the contextual-help "?" button. Used both globally in the topbar (resolving the topic for
 * the current screen by its route) and inline next to a specific field or section (passing a slug).
 *
 * The button is a real link to the full help page (/ayuda/{slug}); help.js progressively enhances it
 * to open the summary popover instead. With no JavaScript it simply navigates to the page, so the
 * help is always reachable. When no topic covers the current screen the function renders nothing.
 */
class HelpExtension extends AbstractExtension
{
    public function __construct(
        private readonly HelpRegistry $registry,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('help_button', $this->helpButton(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * The "?" button for a help topic. With an explicit slug it targets that topic; without one it
     * resolves the topic of the current screen from its route name. Returns an empty string (nothing
     * rendered) when the slug is unknown or the current screen has no associated topic.
     *
     * @param string|null $slug the topic slug, or null to use the current route's topic
     *
     * @return string the button HTML, or '' if there is no topic to link to
     */
    public function helpButton(?string $slug = null): string
    {
        $topic = null !== $slug
            ? $this->registry->bySlug($slug)
            : $this->registry->byRoute($this->currentRoute());

        if (null === $topic) {
            return '';
        }

        $href = $this->urls->generate('help_show', ['slug' => $topic->slug]);
        $label = htmlspecialchars('Ayuda: '.$topic->title, \ENT_QUOTES);

        return sprintf(
            '<a class="help-btn" href="%s" data-help="%s" data-help-title="%s" aria-label="%s" title="%s">?</a>',
            htmlspecialchars($href, \ENT_QUOTES),
            htmlspecialchars($topic->slug, \ENT_QUOTES),
            htmlspecialchars($topic->title, \ENT_QUOTES),
            $label,
            $label,
        );
    }

    /**
     * The name of the route being rendered, or '' outside a request (so lookups just miss).
     */
    private function currentRoute(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request ? (string) $request->attributes->get('_route') : '';
    }
}
