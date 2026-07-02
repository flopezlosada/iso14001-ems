<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Adds an optional {@code help_slug} option to every form field, exposed to templates as
 * {@code field.vars.help_slug}. It lets a form declare, per field, which help topic explains that
 * field so the shared form macro can render a contextual "?" next to its label — without touching
 * each template. Declaring {@code 'help_slug' => 'aspecto-frecuencia'} on a field is all it takes.
 *
 * @see \App\Twig\HelpExtension::helpButton() the "?" the macro renders from this slug
 */
class HelpSlugTypeExtension extends AbstractTypeExtension
{
    /**
     * Applies to the base form type, so every field (which inherits from it) gains the option.
     *
     * @return iterable<class-string>
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('help_slug', null);
        $resolver->setAllowedTypes('help_slug', ['null', 'string']);
    }

    /**
     * Exposes the chosen slug to the field's view so the form macro can read it.
     *
     * @param array<string, mixed> $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['help_slug'] = $options['help_slug'];
    }
}
