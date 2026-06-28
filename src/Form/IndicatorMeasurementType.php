<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\IndicatorMeasurement;
use App\Util\DecimalFormatter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit one periodic {@see IndicatorMeasurement}. The parent indicator comes
 * from the route, not the form.
 *
 * @extends AbstractType<IndicatorMeasurement>
 */
class IndicatorMeasurementType extends AbstractType
{
    private const SPANISH_MONTHS = [
        'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4, 'Mayo' => 5, 'Junio' => 6,
        'Julio' => 7, 'Agosto' => 8, 'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('year', IntegerType::class, [
                'label' => 'Año',
            ])
            ->add('month', ChoiceType::class, [
                'label' => 'Mes',
                'choices' => self::SPANISH_MONTHS,
                'help' => 'Para indicadores anuales, usa el mes en que se realiza la medición.',
            ])
            ->add('value', TextType::class, [
                'label' => 'Valor',
                'attr' => ['inputmode' => 'decimal'],
                'help' => 'Usa el punto para decimales.',
            ])
            ->add('breached', CheckboxType::class, [
                'label' => 'Transgrede el valor de referencia',
                'required' => false,
                'help' => 'Márcalo si el valor incumple el umbral; podrás abrir una no conformidad desde el detalle.',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observaciones',
                'required' => false,
            ]);

        // The value is a DECIMAL, so editing an existing measurement would otherwise show the raw
        // "150.000" in the input. Strip the trailing zeros on the way to the form; the value reaches
        // the model unchanged (Doctrine re-pads it to the column scale on persist).
        $builder->get('value')->addModelTransformer(new CallbackTransformer(
            static fn (string|int|float|null $stored): string => DecimalFormatter::display($stored),
            static fn (?string $input): ?string => $input,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IndicatorMeasurement::class,
        ]);
    }
}
