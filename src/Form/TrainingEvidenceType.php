<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TrainingAction;
use App\Entity\TrainingEvidence;
use App\Repository\TrainingActionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to record or edit a {@see TrainingEvidence} of the environmental training evidence register.
 *
 * @extends AbstractType<TrainingEvidence>
 */
class TrainingEvidenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('personName', TextType::class, [
                'label' => 'Nombre',
                'help' => 'Persona que recibió la formación.',
            ])
            ->add('trainingDescription', TextType::class, [
                'label' => 'Tipo de formación',
                'help' => 'Formación recibida (p. ej. «Sensibilización ambiental ISO 14001»).',
            ])
            ->add('trainingDate', DateType::class, [
                'label' => 'Fecha de la formación',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('questionnaireCompleted', CheckboxType::class, [
                'label' => 'Cuestionario de aprovechamiento realizado',
                'required' => false,
            ])
            ->add('trainingAction', EntityType::class, [
                'class' => TrainingAction::class,
                'label' => 'Acción del plan de formación',
                'required' => false,
                'placeholder' => 'Sin vincular',
                // Show the action with its plan year so same-named actions across years are distinguishable.
                'choice_label' => static fn (TrainingAction $a): string => sprintf('%s (%d)', $a->getDescription(), $a->getPlanYear()),
                'query_builder' => static fn (TrainingActionRepository $r) => $r->createQueryBuilder('a')
                    ->orderBy('a.planYear', 'DESC')
                    ->addOrderBy('a.description', 'ASC'),
                'help' => 'Vincula la evidencia a una acción del plan cuando la formación proviene de él (opcional).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TrainingEvidence::class,
        ]);
    }
}
