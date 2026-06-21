<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProcessArea;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to create or rename a {@see ProcessArea} of the configurable catalogue.
 *
 * @extends AbstractType<ProcessArea>
 */
class ProcessAreaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre del área',
                'help' => 'P. ej. Formación, Secretaría, Dirección, Mantenimiento.',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Activa',
                'required' => false,
                'help' => 'Las áreas inactivas no se pueden asignar a nuevos riesgos, pero se conservan en el histórico.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProcessArea::class,
        ]);
    }
}
