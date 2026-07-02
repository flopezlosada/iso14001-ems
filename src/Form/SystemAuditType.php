<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SystemAudit;
use App\Enum\AuditType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Form to register or edit a {@see SystemAudit} (PC.09.0). The report file is handled outside the
 * entity by the controller, so it is an unmapped field.
 *
 * @extends AbstractType<SystemAudit>
 */
class SystemAuditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('year', IntegerType::class, [
                'label' => 'Año',
                'help' => 'Año del programa/ciclo de auditoría, p. ej. 2025.',
            ])
            ->add('type', EnumType::class, [
                'class' => AuditType::class,
                'label' => 'Tipo',
                'choice_label' => static fn (AuditType $t): string => $t->label(),
                'help_slug' => 'auditoria-tipo',
            ])
            ->add('conductedOn', DateType::class, [
                'label' => 'Fecha de realización',
                'required' => false,
                'widget' => 'single_text',
                // The entity stores an immutable date; without this the form would hand back a
                // mutable \DateTime and the typed setter would fail.
                'input' => 'datetime_immutable',
            ])
            ->add('auditor', TextType::class, [
                'label' => 'Auditor',
                'help' => 'Auditor líder o entidad certificadora que la realiza.',
            ])
            ->add('objective', TextareaType::class, [
                'label' => 'Objetivo',
                'required' => false,
            ])
            ->add('scope', TextareaType::class, [
                'label' => 'Alcance',
                'required' => false,
            ])
            ->add('conclusions', TextareaType::class, [
                'label' => 'Conclusiones',
                'required' => false,
                'help' => 'Conclusiones del equipo auditor (conformidades y oportunidades de mejora).',
            ])
            ->add('reportFile', FileType::class, [
                'label' => 'Informe de auditoría (PDF)',
                'mapped' => false,
                'required' => false,
                'help' => 'Adjunta el informe. Sustituye al anterior si ya había uno.',
                'constraints' => [
                    new File(maxSize: '12M', mimeTypes: ['application/pdf', 'image/jpeg', 'image/png'], mimeTypesMessage: 'Sube un PDF o una imagen.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SystemAudit::class,
        ]);
    }
}
