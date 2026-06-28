<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TrainingAction;
use App\Enum\TrainingType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to capture or edit a single {@see TrainingAction} of the annual training plan. The plan
 * year is set by the controller from the route, so it is not part of the form.
 *
 * @extends AbstractType<TrainingAction>
 */
class TrainingActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, ['label' => 'Descripción del curso'])
            ->add('type', EnumType::class, [
                'class' => TrainingType::class,
                'label' => 'Tipo',
                'choice_label' => static fn (TrainingType $type): string => $type->label(),
                'required' => false,
                'placeholder' => '— Sin clasificar —',
            ])
            ->add('targetAudience', TextType::class, [
                'label' => 'Dirigido a',
                'help' => 'Profesionales o puestos a los que va dirigida la acción.',
            ])
            ->add('objectives', TextareaType::class, ['label' => 'Objetivos'])
            ->add('plannedDate', DateType::class, [
                'label' => 'Fecha prevista de ejecución',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('methodology', TextareaType::class, ['label' => 'Metodología de formación'])
            ->add('actualDate', DateType::class, [
                'label' => 'Fecha real de ejecución',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('efficacyEvaluation', TextareaType::class, [
                'label' => 'Evaluación de la eficacia',
                'required' => false,
            ])
            ->add('reviewNote', TextareaType::class, [
                'label' => 'Nota de revisión',
                'help' => 'Motivo por el que quedó marcada para revisar (generado por la importación; puedes ajustarlo).',
                'required' => false,
            ])
            ->add('needsReview', CheckboxType::class, [
                'label' => 'Pendiente de revisar',
                'help' => 'Desmárcalo cuando hayas verificado y corregido los datos de esta acción.',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TrainingAction::class,
        ]);
    }
}
