<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RiskAction;
use App\Entity\Role;
use App\Repository\RoleRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for one {@see RiskAction} of the action plan, embedded as a collection item in
 * {@see RiskAssessmentType}. The responsible is a {@see Role} (functional ownership); deadline and
 * efficacy stay free text by design.
 *
 * @extends AbstractType<RiskAction>
 */
class RiskActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Acción',
            ])
            ->add('responsible', EntityType::class, [
                'label' => 'Responsable',
                'required' => false,
                'class' => Role::class,
                'choice_label' => 'name',
                'placeholder' => 'Sin asignar',
                // Alphabetical, stable order for the selector.
                'query_builder' => static fn (RoleRepository $roles): QueryBuilder => $roles->createQueryBuilder('r')->orderBy('r.name', 'ASC'),
            ])
            ->add('deadline', TextType::class, [
                'label' => 'Plazo',
                'required' => false,
                'help' => 'Texto libre, p. ej. "Diciembre", "Anual".',
            ])
            ->add('efficacy', TextareaType::class, [
                'label' => 'Evaluación de la eficacia',
                'required' => false,
            ])
            ->add('evaluatedAt', DateType::class, [
                'label' => 'Fecha de evaluación',
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RiskAction::class,
        ]);
    }
}
