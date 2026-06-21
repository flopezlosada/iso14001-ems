<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SupplierIncident;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit a {@see SupplierIncident}. The parent supplier comes from the route,
 * not the form.
 *
 * @extends AbstractType<SupplierIncident>
 */
class SupplierIncidentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('occurredOn', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
            ])
            ->add('resolution', TextareaType::class, [
                'label' => 'Resolución',
                'required' => false,
                'help' => 'Acciones tomadas (puede completarse más tarde).',
            ])
            ->add('severe', CheckboxType::class, [
                'label' => 'Grave o repetitiva',
                'required' => false,
                'help' => 'Si lo es, valora abrir una no conformidad desde el detalle del proveedor.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SupplierIncident::class,
        ]);
    }
}
