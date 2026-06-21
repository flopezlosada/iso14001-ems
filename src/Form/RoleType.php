<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Role;
use App\Enum\Area;
use App\Enum\PermissionLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Admin form to create/edit a role and its per-area permission matrix. One (unmapped) level
 * selector is added per {@see Area}; the controller writes the chosen levels back via setLevel().
 *
 * @extends AbstractType<Role>
 */
class RoleType extends AbstractType
{
    public const PERMISSION_PREFIX = 'perm_';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Código',
                'help' => 'Identificador corto y estable, p. ej. "rsgma" o "secretaria".',
            ])
            ->add('name', TextType::class, ['label' => 'Nombre'])
            ->add('description', TextareaType::class, ['label' => 'Descripción', 'required' => false]);

        // One permission selector per area, pre-filled with the role's current level. Unmapped:
        // the controller reads them and calls Role::setLevel().
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $form = $event->getForm();
            $role = $event->getData();

            foreach (Area::cases() as $area) {
                $form->add(self::PERMISSION_PREFIX.$area->value, EnumType::class, [
                    'class' => PermissionLevel::class,
                    'label' => $area->label(),
                    'mapped' => false,
                    'data' => $role instanceof Role ? $role->getLevel($area) : PermissionLevel::NONE,
                    'choice_label' => static fn (PermissionLevel $level): string => $level->label(),
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Role::class]);
    }
}
