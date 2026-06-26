<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DafoAnalysis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit a {@see DafoAnalysis} (register "F.06.0 DAFO"). The four quadrants are
 * free text (one item per line); only the school year is required.
 *
 * @extends AbstractType<DafoAnalysis>
 */
class DafoAnalysisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('schoolYear', TextType::class, [
                'label' => 'Ejercicio (curso escolar)',
                'help' => 'Curso escolar, p. ej. 2025-2026.',
            ])
            ->add('weaknesses', TextareaType::class, [
                'label' => 'Debilidades (interno −)',
                'required' => false,
                'help' => 'Un punto por línea.',
            ])
            ->add('threats', TextareaType::class, [
                'label' => 'Amenazas (externo −)',
                'required' => false,
                'help' => 'Un punto por línea.',
            ])
            ->add('strengths', TextareaType::class, [
                'label' => 'Fortalezas (interno +)',
                'required' => false,
                'help' => 'Un punto por línea.',
            ])
            ->add('opportunities', TextareaType::class, [
                'label' => 'Oportunidades (externo +)',
                'required' => false,
                'help' => 'Un punto por línea.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DafoAnalysis::class,
        ]);
    }
}
