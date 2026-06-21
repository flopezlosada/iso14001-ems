<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RiskAssessment;
use App\Enum\RiskLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
            ->add('exercise', TextType::class, [
                'label' => 'Curso',
                'help' => 'Formato AAAA-AAAA, p. ej. 2025-2026.',
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
            ->add('revisionNumber', IntegerType::class, [
                'label' => 'Nº de revisión',
            ])
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
        ]);
    }
}
