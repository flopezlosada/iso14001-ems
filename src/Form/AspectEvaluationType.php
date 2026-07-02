<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AspectEvaluation;
use App\Enum\AspectType;
use App\Enum\DirectAspectCategory;
use App\Enum\InfluenceLevel;
use App\Enum\ScoreLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit one yearly {@see AspectEvaluation}. The criteria shown depend on the
 * aspect type (passed as the "aspect_type" option): direct → frequency/intensity/hazard;
 * abnormal → probability/control/severity; indirect → influence plus a manual significant flag
 * (the procedure defines no threshold for indirect aspects). The significance sum is computed on
 * save. The parent aspect comes from the route.
 *
 * @extends AbstractType<AspectEvaluation>
 */
class AspectEvaluationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $scoreLabel = static fn (ScoreLevel $level): string => sprintf('%s (%d)', $level->label(), $level->value);
        $type = $options['aspect_type'];

        $builder->add('year', IntegerType::class, ['label' => 'Año']);

        if (AspectType::ABNORMAL === $type) {
            $builder
                ->add('probability', EnumType::class, [
                    'class' => ScoreLevel::class, 'label' => 'Probabilidad de ocurrencia',
                    'required' => false, 'placeholder' => 'Sin evaluar', 'choice_label' => $scoreLabel,
                    'help_slug' => 'aspecto-probabilidad',
                ])
                ->add('control', EnumType::class, [
                    'class' => ScoreLevel::class, 'label' => 'Capacidad de control',
                    'required' => false, 'placeholder' => 'Sin evaluar', 'choice_label' => $scoreLabel,
                    'help_slug' => 'aspecto-control',
                ])
                ->add('severity', EnumType::class, [
                    'class' => ScoreLevel::class, 'label' => 'Severidad de las consecuencias',
                    'required' => false, 'placeholder' => 'Sin evaluar', 'choice_label' => $scoreLabel,
                    'help_slug' => 'aspecto-severidad',
                ]);
        } elseif (AspectType::INDIRECT === $type) {
            $builder
                ->add('influence', EnumType::class, [
                    'class' => InfluenceLevel::class, 'label' => 'Capacidad de influencia',
                    'required' => false, 'placeholder' => 'Sin evaluar',
                    'choice_label' => static fn (InfluenceLevel $l): string => sprintf('%s (%d)', $l->label(), $l->value),
                    'help_slug' => 'aspecto-influencia',
                ])
                ->add('significant', CheckboxType::class, [
                    'label' => 'Significativo',
                    'required' => false,
                    'help' => 'El procedimiento no define un umbral para aspectos indirectos: márcalo manualmente.',
                ]);
        } else {
            // Discharges (vertidos) only define BAJA/ALTA for hazard (PG-06.01 Anexo I); the rest
            // use the full scale. Intensity, by contrast, now applies to every category (incl.
            // discharges, since RG-06.01.01 Rev 02). With no category yet, offer all levels.
            $category = $options['category'];
            $hazardChoices = $category instanceof DirectAspectCategory ? $category->hazardLevels() : ScoreLevel::cases();

            $builder
                ->add('frequency', EnumType::class, [
                    'class' => ScoreLevel::class, 'label' => 'Frecuencia',
                    'required' => false, 'placeholder' => 'Sin evaluar', 'choice_label' => $scoreLabel,
                    'help_slug' => 'aspecto-frecuencia',
                ])
                ->add('intensity', EnumType::class, [
                    'class' => ScoreLevel::class, 'label' => 'Intensidad',
                    'required' => false, 'placeholder' => 'Sin dato (se computa como 4)', 'choice_label' => $scoreLabel,
                    'help' => 'Déjalo vacío si no hay dato del año anterior: cuenta como 4 («Media»).',
                    'help_slug' => 'aspecto-intensidad',
                ])
                ->add('hazard', EnumType::class, [
                    'class' => ScoreLevel::class, 'label' => 'Peligrosidad',
                    'required' => false, 'placeholder' => 'Sin evaluar', 'choice_label' => $scoreLabel,
                    'choices' => $hazardChoices,
                    'help_slug' => 'aspecto-peligrosidad',
                ]);
        }

        $builder->add('notes', TextareaType::class, ['label' => 'Observaciones', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AspectEvaluation::class,
            'aspect_type' => AspectType::DIRECT,
            'category' => null,
        ]);
        $resolver->setAllowedTypes('aspect_type', AspectType::class);
        $resolver->setAllowedTypes('category', [DirectAspectCategory::class, 'null']);
    }
}
