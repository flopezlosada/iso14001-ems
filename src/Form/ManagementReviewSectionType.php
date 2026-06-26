<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManagementReviewSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One editable section of a management review. Only the text is editable; the section key and order
 * are fixed. The section's heading is rendered from the entity in the template.
 *
 * @extends AbstractType<ManagementReviewSection>
 */
class ManagementReviewSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', TextareaType::class, [
            'label' => false,
            'required' => false,
            'attr' => ['rows' => 5],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ManagementReviewSection::class]);
    }
}
