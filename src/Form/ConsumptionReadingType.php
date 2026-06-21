<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ConsumptionReading;
use App\Enum\ConsumptionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Form to capture or edit a single monthly {@see ConsumptionReading}. The period year is set by
 * the controller from the route, so it is not part of the form.
 *
 * @extends AbstractType<ConsumptionReading>
 */
class ConsumptionReadingType extends AbstractType
{
    private const SPANISH_MONTHS = [
        'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4, 'Mayo' => 5, 'Junio' => 6,
        'Julio' => 7, 'Agosto' => 8, 'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => ConsumptionType::class,
                'label' => 'Tipo de consumo',
                'choice_label' => static fn (ConsumptionType $type): string => $type->label(),
            ])
            ->add('periodMonth', ChoiceType::class, [
                'label' => 'Mes',
                'choices' => self::SPANISH_MONTHS,
            ])
            ->add('quantity', TextType::class, [
                'label' => 'Cantidad',
                'attr' => ['inputmode' => 'decimal'],
                'help' => 'En la unidad del tipo de consumo (kWh, m³, litros, paquetes, cartuchos). Usa el punto para decimales.',
            ])
            ->add('cost', TextType::class, [
                'label' => 'Coste (€)',
                'required' => false,
                'attr' => ['inputmode' => 'decimal'],
                'help' => 'Déjalo vacío para el tóner (no registra coste). Usa el punto para decimales.',
            ])
            ->add('notes', TextType::class, [
                'label' => 'Observaciones',
                'required' => false,
            ])
            // Not mapped: the entity stores the path, not the uploaded file. The controller moves
            // the file via FileUploader and fills the path. Validation lives here (KISS, idiomatic).
            ->add('invoiceFile', FileType::class, [
                'label' => 'Factura (PDF o imagen)',
                'mapped' => false,
                'required' => false,
                'help' => 'Opcional. Adjunta la factura como evidencia de la lectura. Máx. 8 MB.',
                'constraints' => [
                    new File(
                        maxSize: '8M',
                        mimeTypes: ['application/pdf', 'image/jpeg', 'image/png'],
                        mimeTypesMessage: 'Sube un PDF o una imagen (JPG/PNG).',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConsumptionReading::class,
        ]);
    }
}
