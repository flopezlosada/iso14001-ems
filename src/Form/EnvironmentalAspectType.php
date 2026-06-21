<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EnvironmentalAspect;
use App\Enum\DirectAspectCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit a direct {@see EnvironmentalAspect} (catalogue entry). Yearly
 * evaluations are managed separately on the aspect detail page.
 *
 * @extends AbstractType<EnvironmentalAspect>
 */
class EnvironmentalAspectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Aspecto ambiental',
            ])
            ->add('category', EnumType::class, [
                'class' => DirectAspectCategory::class,
                'label' => 'Categoría',
                'choice_label' => static fn (DirectAspectCategory $c): string => $c->label(),
            ])
            ->add('unit', TextType::class, [
                'label' => 'Unidad de medida',
                'required' => false,
                'help' => 'P. ej. kWh, m³, Kg, litros.',
            ])
            ->add('associatedImpact', TextType::class, [
                'label' => 'Impacto asociado',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EnvironmentalAspect::class,
        ]);
    }
}
