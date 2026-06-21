<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WasteRecord;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to capture or edit a {@see WasteRecord} (a waste pick-up entry).
 *
 * @extends AbstractType<WasteRecord>
 */
class WasteRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lerCode', TextType::class, [
                'label' => 'Código LER',
                'help' => 'Código del Catálogo Europeo de Residuos (p. ej. 200121).',
            ])
            ->add('description', TextType::class, ['label' => 'Residuo'])
            ->add('quantityKg', TextType::class, [
                'label' => 'Cantidad (kg)',
                'attr' => ['inputmode' => 'decimal'],
                'help' => 'Usa el punto para los decimales.',
            ])
            ->add('pickupDate', DateType::class, [
                'label' => 'Fecha de retirada',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('manager', TextType::class, ['label' => 'Gestor autorizado'])
            ->add('hazardous', CheckboxType::class, ['label' => 'Residuo peligroso', 'required' => false])
            ->add('notes', TextareaType::class, ['label' => 'Observaciones', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WasteRecord::class]);
    }
}
