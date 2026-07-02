<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SupplierEvaluation;
use App\Enum\SupplierCriterion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit one yearly {@see SupplierEvaluation}. The parent supplier comes from
 * the route, not the form.
 *
 * @extends AbstractType<SupplierEvaluation>
 */
class SupplierEvaluationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('year', IntegerType::class, [
                'label' => 'Año',
            ])
            ->add('criterion', EnumType::class, [
                'class' => SupplierCriterion::class,
                'label' => 'Criterio',
                'choice_label' => static fn (SupplierCriterion $c): string => sprintf('%s (%s)', $c->label(), $c->statusLabel()),
                'help' => 'El estado (Aprobado / No aprobado) se deriva del criterio.',
                'help_slug' => 'proveedor-evaluacion',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observaciones',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SupplierEvaluation::class,
        ]);
    }
}
