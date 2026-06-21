<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Indicator;
use App\Enum\MeasurementPeriodicity;
use App\Enum\SgmaProcess;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit an {@see Indicator} (catalogue entry). Measurements are managed
 * separately on the indicator detail page.
 *
 * @extends AbstractType<Indicator>
 */
class IndicatorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Indicador',
            ])
            ->add('process', EnumType::class, [
                'class' => SgmaProcess::class,
                'label' => 'Proceso',
                'choice_label' => static fn (SgmaProcess $p): string => $p->label(),
            ])
            ->add('measurementDescription', TextareaType::class, [
                'label' => 'Descripción de la medición',
                'required' => false,
                'help' => 'Cómo se mide / fórmula (p. ej. "% de objetivos conseguidos respecto a los definidos").',
            ])
            ->add('referenceValue', TextType::class, [
                'label' => 'Valor de referencia',
                'required' => false,
                'help' => 'Umbral u objetivo (p. ej. "5%", "0", "NINGUNA").',
            ])
            ->add('periodicity', EnumType::class, [
                'class' => MeasurementPeriodicity::class,
                'label' => 'Periodicidad',
                'choice_label' => static fn (MeasurementPeriodicity $p): string => $p->label(),
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Indicator::class,
        ]);
    }
}
