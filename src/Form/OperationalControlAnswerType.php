<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\OperationalControlAnswer;
use App\Enum\ConformityResult;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One row of the operational-control checklist: just the conformity result. The item it refers to is
 * fixed (pre-filled by the controller) and rendered from the bound data, not editable here.
 *
 * @extends AbstractType<OperationalControlAnswer>
 */
class OperationalControlAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('result', EnumType::class, [
            'class' => ConformityResult::class,
            'label' => false,
            'expanded' => true,
            'required' => false,
            'placeholder' => 'Sin evaluar',
            'choice_label' => static fn (ConformityResult $r): string => $r->label(),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OperationalControlAnswer::class,
        ]);
    }
}
