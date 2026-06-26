<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManagementReview;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for a management review: the meeting metadata (course, date, participants) and the fixed set
 * of review sections. The course is locked once the review exists, since it is the key the section
 * snapshots were generated for.
 *
 * @extends AbstractType<ManagementReview>
 */
class ManagementReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('exercise', TextType::class, [
                'label' => 'Curso',
                'help' => 'Formato AAAA-AAAA, p. ej. 2025-2026.',
                'disabled' => true === $options['lock_exercise'],
            ])
            ->add('meetingDate', DateType::class, [
                'label' => 'Fecha de la reunión',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('participants', EntityType::class, [
                'label' => 'Participantes',
                'class' => User::class,
                'choice_label' => 'fullName',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('sections', CollectionType::class, [
                'entry_type' => ManagementReviewSectionType::class,
                'label' => false,
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ManagementReview::class,
            'lock_exercise' => false,
        ]);
        $resolver->setAllowedTypes('lock_exercise', 'bool');
    }
}
