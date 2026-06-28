<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Settings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for the business {@see Settings}: the per-category significance thresholds of the aspect
 * engine (PG-06.01) and the auto-intensity bounds. The intensity rise/drop are shown as percentages
 * (the stored 0.10 reads as 10).
 *
 * @extends AbstractType<Settings>
 */
class SettingsType extends AbstractType
{
    /** Nombre de mes → número (1-12), para el selector del mes de inicio del calendario. */
    private const MONTHS = [
        'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4, 'Mayo' => 5, 'Junio' => 6,
        'Julio' => 7, 'Agosto' => 8, 'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('consumptionThreshold', IntegerType::class, [
                'label' => 'Umbral de significancia · Consumos',
                'help' => 'Un aspecto es significativo si su puntuación supera este valor (suma de 3 criterios, máx. 18).',
            ])
            ->add('emissionThreshold', IntegerType::class, ['label' => 'Umbral de significancia · Emisiones'])
            ->add('wasteThreshold', IntegerType::class, ['label' => 'Umbral de significancia · Residuos'])
            ->add('dischargeThreshold', IntegerType::class, ['label' => 'Umbral de significancia · Vertidos'])
            ->add('abnormalThreshold', IntegerType::class, [
                'label' => 'Umbral de significancia · Anormales y por defecto',
                'help' => 'Se aplica a los aspectos anormales y a un aspecto directo sin categoría.',
            ])
            ->add('intensityRiseThreshold', PercentType::class, [
                'label' => 'Auto-intensidad · subida para "Alta"',
                'type' => 'fractional',
                'scale' => 1,
                'help' => 'Si el consumo sube más de este porcentaje frente a la referencia, la intensidad se sugiere ALTA.',
            ])
            ->add('intensityDropThreshold', PercentType::class, [
                'label' => 'Auto-intensidad · bajada para "Baja"',
                'type' => 'fractional',
                'scale' => 1,
                'help' => 'Si el consumo baja más de este porcentaje, la intensidad se sugiere BAJA.',
            ])
            ->add('intensityBaselineYears', IntegerType::class, [
                'label' => 'Auto-intensidad · años de referencia (N)',
                'help' => 'Con cuántos años anteriores se compara (1 = solo el año anterior).',
            ])
            ->add('autoNcFromBreachedIndicators', CheckboxType::class, [
                'label' => 'No conformidad automática · Indicadores fuera de umbral',
                'required' => false,
                'help' => 'Abre una no conformidad por cada medición marcada como fuera de su valor de referencia.',
            ])
            ->add('autoNcFromUnmetObjectives', CheckboxType::class, [
                'label' => 'No conformidad automática · Objetivos no cumplidos',
                'required' => false,
                'help' => 'Abre una no conformidad por cada objetivo marcado como no cumplido.',
            ])
            ->add('startMonth', ChoiceType::class, [
                'label' => 'Calendario anual · mes de inicio',
                'help' => 'Mes por el que empieza el calendario anual, para abarcar el ciclo de auditoría del centro. Solo afecta a la presentación del calendario.',
                'choices' => self::MONTHS,
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Settings::class,
        ]);
    }
}
