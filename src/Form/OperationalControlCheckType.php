<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\OperationalControlCheck;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for one monthly operational-control inspection (PG-08.01): the period and who carried it out,
 * plus the checklist answers (pre-filled, one per active catalogue item) and free observations.
 *
 * @extends AbstractType<OperationalControlCheck>
 */
class OperationalControlCheckType extends AbstractType
{
    private const array MONTHS = [
        'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4, 'Mayo' => 5, 'Junio' => 6,
        'Julio' => 7, 'Agosto' => 8, 'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('periodYear', IntegerType::class, ['label' => 'Año'])
            ->add('periodMonth', ChoiceType::class, ['label' => 'Mes', 'choices' => self::MONTHS])
            ->add('performedBy', TextType::class, ['label' => 'Realizado por'])
            ->add('answers', CollectionType::class, [
                'entry_type' => OperationalControlAnswerType::class,
                'label' => false,
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
            ])
            ->add('observations', TextareaType::class, [
                'label' => 'Observaciones',
                'required' => false,
                'help' => 'Detalla cualquier ítem marcado como "No conforme".',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperationalControlCheck::class,
        ]);
    }
}
