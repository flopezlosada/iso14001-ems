<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AspectEvaluation;
use App\Enum\ScoreLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit one yearly {@see AspectEvaluation}. The three criteria are scored
 * 2/4/6; the significance sum and flag are computed on save, so they are not in the form. The
 * parent aspect comes from the route.
 *
 * @extends AbstractType<AspectEvaluation>
 */
class AspectEvaluationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $scoreLabel = static fn (ScoreLevel $level): string => sprintf('%s (%d)', $level->label(), $level->value);

        $builder
            ->add('year', IntegerType::class, [
                'label' => 'Año',
            ])
            ->add('frequency', EnumType::class, [
                'class' => ScoreLevel::class,
                'label' => 'Frecuencia',
                'required' => false,
                'placeholder' => 'Sin evaluar',
                'choice_label' => $scoreLabel,
            ])
            ->add('intensity', EnumType::class, [
                'class' => ScoreLevel::class,
                'label' => 'Intensidad',
                'required' => false,
                'placeholder' => 'Sin dato (se computa como 4)',
                'choice_label' => $scoreLabel,
                'help' => 'Para vertidos no aplica. En consumos/residuos, déjalo vacío si no hay dato del año anterior (cuenta como 4).',
            ])
            ->add('hazard', EnumType::class, [
                'class' => ScoreLevel::class,
                'label' => 'Peligrosidad',
                'required' => false,
                'placeholder' => 'Sin evaluar',
                'choice_label' => $scoreLabel,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observaciones',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AspectEvaluation::class,
        ]);
    }
}
