<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InterestedParty;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit an {@see InterestedParty} (register F.04.0 / PPI). The review year is
 * fixed by the controller from the route, so it is not part of the form.
 *
 * @extends AbstractType<InterestedParty>
 */
class InterestedPartyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Parte interesada',
                'help' => 'Quién es la parte interesada (p. ej. "Usuarios/Alumnos", "Proveedores").',
            ])
            ->add('needsAndExpectations', TextareaType::class, [
                'label' => 'Necesidades y expectativas',
                'help' => 'Qué necesita y espera esta parte interesada del centro.',
            ])
            ->add('incidents', TextareaType::class, [
                'label' => 'Incidencias',
                'required' => false,
                // Store a blank field as NULL (not an empty string), matching the nullable column.
                'empty_data' => null,
                'help' => 'Incidencias detectadas en la revisión. Texto libre (p. ej. "NO" si no hay).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterestedParty::class,
        ]);
    }
}
