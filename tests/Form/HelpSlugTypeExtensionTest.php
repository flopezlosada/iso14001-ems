<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\Extension\HelpSlugTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Unit tests for {@see HelpSlugTypeExtension}: the extension must add a {@code help_slug} option to
 * every field and expose it on the view so the form macro can render the contextual "?". Pins the
 * option name and the null default, since the macro reads {@code field.vars.help_slug} by that name.
 */
final class HelpSlugTypeExtensionTest extends TypeTestCase
{
    /**
     * @return list<HelpSlugTypeExtension>
     */
    protected function getTypeExtensions(): array
    {
        return [new HelpSlugTypeExtension()];
    }

    public function testHelpSlugIsExposedOnTheView(): void
    {
        $view = $this->factory->create(FormType::class, null, ['help_slug' => 'aspecto-frecuencia'])->createView();

        self::assertSame('aspecto-frecuencia', $view->vars['help_slug']);
    }

    public function testHelpSlugDefaultsToNull(): void
    {
        // A field that declares no help_slug still gets the var (null), so the macro's lookup is safe
        // even under strict_variables.
        $view = $this->factory->create(FormType::class)->createView();

        self::assertNull($view->vars['help_slug']);
    }
}
