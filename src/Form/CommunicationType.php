<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Communication;
use App\Entity\InterestedParty;
use App\Enum\CommunicationCategory;
use App\Enum\CommunicationChannel;
use App\Enum\CommunicationScope;
use App\Repository\InterestedPartyRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form to register or edit a {@see Communication} of the register RG-07.04.00 (PC.04.0, §7.4).
 *
 * @extends AbstractType<Communication>
 */
class CommunicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('occurredOn', DateType::class, [
                'label' => 'Fecha',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('scope', EnumType::class, [
                'class' => CommunicationScope::class,
                'label' => 'Ámbito',
                'choice_label' => static fn (CommunicationScope $s): string => $s->label(),
                'help' => 'Interna (dentro del centro) o externa (con partes de fuera).',
            ])
            ->add('category', EnumType::class, [
                'class' => CommunicationCategory::class,
                'label' => 'Tipo de comunicación',
                'choice_label' => static fn (CommunicationCategory $c): string => $c->label(),
            ])
            ->add('channel', EnumType::class, [
                'class' => CommunicationChannel::class,
                'label' => 'Canal',
                'choice_label' => static fn (CommunicationChannel $c): string => $c->label(),
            ])
            ->add('subject', TextType::class, [
                'label' => 'Mensaje',
                'help' => 'Asunto o resumen breve de la comunicación.',
            ])
            ->add('details', TextareaType::class, [
                'label' => 'Detalle',
                'required' => false,
                'empty_data' => null,
                'help' => 'Contenido completo de la comunicación (opcional).',
            ])
            ->add('sender', TextType::class, [
                'label' => 'Emisor',
                'required' => false,
                'empty_data' => null,
                'help' => 'Quién emite la comunicación (opcional).',
            ])
            ->add('recipient', TextType::class, [
                'label' => 'Receptor',
                'required' => false,
                'empty_data' => null,
                'help' => 'Quién la recibe (opcional).',
            ])
            ->add('interestedParty', EntityType::class, [
                'class' => InterestedParty::class,
                'label' => 'Parte interesada relacionada',
                'required' => false,
                'placeholder' => 'Sin relacionar',
                // Show the party with its review year so same-named parties across years are distinguishable.
                'choice_label' => static fn (InterestedParty $p): string => sprintf('%s (%d)', $p->getName(), $p->getReviewYear()),
                'query_builder' => static fn (InterestedPartyRepository $r) => $r->createQueryBuilder('p')
                    ->orderBy('p.reviewYear', 'DESC')
                    ->addOrderBy('p.name', 'ASC'),
                'help' => 'Enlaza la comunicación a una parte interesada cuando proceda (p. ej. una queja).',
            ])
            ->add('response', TextareaType::class, [
                'label' => 'Respuesta',
                'required' => false,
                'empty_data' => null,
                'help' => 'Cómo se respondió o cerró (puede completarse más tarde; importante en quejas).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Communication::class,
        ]);
    }
}
