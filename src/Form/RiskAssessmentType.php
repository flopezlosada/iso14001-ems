<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RiskAssessment;
use App\Enum\RiskLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit one {@see RiskAssessment} (a school-year valuation) together with its
 * action plan. The score and category are computed on save, so they are not in the form. The
 * parent item comes from the route.
 *
 * @extends AbstractType<RiskAssessment>
 */
class RiskAssessmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $levelLabel = static fn (RiskLevel $level): string => sprintf('%s (%d)', $level->label(), $level->value);

        $builder
            // The course is a closed set of valid school years (not free text), so a typo or an
            // invented year is impossible. On edit it is locked: a valuation never changes the year
            // it belongs to (that is what the per-course unique constraint protects), so a correction
            // is an edit of the same year, never a move to another.
            ->add('exercise', ChoiceType::class, [
                'label' => 'Curso',
                'choices' => array_combine($options['exercise_choices'], $options['exercise_choices']),
                'placeholder' => false,
                'disabled' => true === $options['lock_exercise'],
            ])
            ->add('probability', EnumType::class, [
                'class' => RiskLevel::class,
                'label' => 'Probabilidad / Potencialidad',
                'help' => 'Probabilidad para riesgos, potencialidad para oportunidades.',
                'choice_label' => $levelLabel,
            ])
            ->add('impact', EnumType::class, [
                'class' => RiskLevel::class,
                'label' => 'Impacto',
                'choice_label' => $levelLabel,
            ])
            ->add('justification', TextareaType::class, [
                'label' => 'Motivo / Justificación',
                'required' => false,
            ])
            // The revision number is not user-editable: it is bumped automatically when an approved
            // valuation is edited (a new revision), in RiskAssessmentController::handleForm().
            ->add('actions', CollectionType::class, [
                'entry_type' => RiskActionType::class,
                'label' => 'Plan de acción',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RiskAssessment::class,
            // The school years offered in the "Curso" selector, in canonical "YYYY-YYYY" format.
            'exercise_choices' => [],
            // When true the "Curso" selector is locked (used on edit: the year is immutable).
            'lock_exercise' => false,
        ]);
        $resolver->setAllowedTypes('exercise_choices', 'string[]');
        $resolver->setAllowedTypes('lock_exercise', 'bool');
    }
}
