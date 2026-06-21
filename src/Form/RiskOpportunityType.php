<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ProcessArea;
use App\Entity\RiskOpportunity;
use App\Enum\RiskOpportunityType as ItemType;
use App\Repository\ProcessAreaRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit a {@see RiskOpportunity} (the identified item). Its yearly valuations
 * are managed separately on the detail page.
 *
 * @extends AbstractType<RiskOpportunity>
 */
class RiskOpportunityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => ItemType::class,
                'label' => 'Tipo',
                'choice_label' => static fn (ItemType $t): string => $t->label(),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
            ])
            ->add('processArea', EntityType::class, [
                'class' => ProcessArea::class,
                'label' => 'Proceso / Área',
                'choice_label' => 'name',
                'query_builder' => static fn (ProcessAreaRepository $r) => $r->createQueryBuilder('a')->where('a.active = true')->orderBy('a.name', 'ASC'),
                'placeholder' => 'Selecciona un área',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RiskOpportunity::class,
        ]);
    }
}
