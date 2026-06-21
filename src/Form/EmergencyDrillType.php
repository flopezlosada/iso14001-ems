<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EmergencyDrill;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to capture or edit an {@see EmergencyDrill} report (record RG-08.02.01).
 *
 * @extends AbstractType<EmergencyDrill>
 */
class EmergencyDrillType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('drillDate', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('emergencyType', TextType::class, [
                'label' => 'Tipo de emergencia simulada',
                'help' => 'P. ej. incendio, derrame de combustible de caldera, evacuación.',
            ])
            ->add('location', TextType::class, ['label' => 'Lugar'])
            ->add('participants', TextareaType::class, ['label' => 'Participantes'])
            ->add('actionProcedure', TextareaType::class, ['label' => 'Procedimiento de actuación'])
            ->add('conclusions', TextareaType::class, ['label' => 'Conclusiones y observaciones'])
            ->add('reportedBy', TextType::class, [
                'label' => 'Informe realizado por',
                'required' => false,
                'help' => 'Responsable del SGMA que firma el informe.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmergencyDrill::class,
        ]);
    }
}
