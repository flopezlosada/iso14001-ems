<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Admin form to register or edit a user (the allow-list entry). No password: the person signs
 * in with the magic link or SSO once their (active) account exists.
 *
 * @extends AbstractType<User>
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nombre y apellidos',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correo electrónico',
                'help' => 'El correo con el que accederá al sistema (su cuenta para el SSO).',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Activo (puede acceder)',
                'required' => false,
            ])
            ->add('assignedRoles', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Roles',
                'required' => false,
                // Use addAssignedRole/removeAssignedRole so unticking a role on edit actually removes it.
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
