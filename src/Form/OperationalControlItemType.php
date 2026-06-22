<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\OperationalControlItem;
use App\Enum\OperationalControlSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to create or edit one checklist catalogue item (PG-08.01). Items are never deleted (past
 * answers reference them); they are deactivated via {@see OperationalControlItem::$active}.
 *
 * @extends AbstractType<OperationalControlItem>
 */
class OperationalControlItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('section', EnumType::class, [
                'class' => OperationalControlSection::class,
                'label' => 'Sección',
                'choice_label' => static fn (OperationalControlSection $s): string => $s->label(),
            ])
            ->add('label', TextType::class, [
                'label' => 'Ítem a verificar',
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Orden',
                'help' => 'Posición dentro del checklist (menor aparece antes).',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
                'help' => 'Desmárcalo para retirarlo del checklist sin borrar el histórico.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperationalControlItem::class,
        ]);
    }
}
