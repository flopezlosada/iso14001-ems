<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CorrectiveAction;
use App\Entity\User;
use App\Enum\Efficacy;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit a {@see CorrectiveAction}. The sequence/reference is assigned by the
 * controller on creation, so it is not part of the form. The parent non-conformity comes from
 * the route, not the form.
 *
 * @extends AbstractType<CorrectiveAction>
 */
class CorrectiveActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activeUsers = static fn (UserRepository $r) => $r->createQueryBuilder('u')
            ->where('u.active = true')
            ->orderBy('u.fullName', 'ASC');

        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Descripción de la acción correctiva',
                'help' => 'Acción o acciones del plan, con su seguimiento y evaluación.',
            ])
            ->add('responsible', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable de la implantación',
                'required' => false,
                'placeholder' => 'Sin asignar',
                'choice_label' => static fn (User $u): string => $u->getFullName(),
                'query_builder' => $activeUsers,
            ])
            ->add('plannedDate', DateType::class, [
                'label' => 'Fecha prevista de implantación',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('implementationEvidence', TextareaType::class, [
                'label' => 'Documentos/registros de la implantación',
                'required' => false,
                'help' => 'Evidencias y registros constatados.',
            ])
            ->add('requiresDirectionAuthorization', CheckboxType::class, [
                'label' => 'Requiere autorización de Dirección',
                'required' => false,
                'help' => 'Marca si implica nuevos recursos, afecta a documentación en vigor o a varios procesos (PC.10.0 §4.3.2).',
            ])
            ->add('authorizedBy', EntityType::class, [
                'class' => User::class,
                'label' => 'Autorizada por',
                'required' => false,
                'placeholder' => 'Sin autorizar',
                'choice_label' => static fn (User $u): string => $u->getFullName(),
                'query_builder' => $activeUsers,
                'help' => 'La fecha de autorización se registra automáticamente al asignar quién autoriza.',
            ])
            ->add('reviewedBy', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable de la revisión',
                'required' => false,
                'placeholder' => 'Sin revisar',
                'choice_label' => static fn (User $u): string => $u->getFullName(),
                'query_builder' => $activeUsers,
            ])
            ->add('reviewedAt', DateType::class, [
                'label' => 'Fecha de la revisión',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('efficacy', EnumType::class, [
                'class' => Efficacy::class,
                'label' => 'Eficacia',
                'required' => false,
                'placeholder' => 'Pendiente de evaluar',
                'choice_label' => static fn (Efficacy $e): string => $e->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CorrectiveAction::class,
        ]);
    }
}
