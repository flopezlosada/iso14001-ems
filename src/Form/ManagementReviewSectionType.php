<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManagementReviewSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One section of a management review. The manually written sections are editable; the
 * auto-generated ones (those with a provider, listed in the `auto_keys` option) carry a snapshot of
 * other modules' data and must only be reviewed, so their text field is disabled here — a tampered
 * POST cannot overwrite them. The section's heading is rendered from the entity in the template.
 *
 * @extends AbstractType<ManagementReviewSection>
 */
class ManagementReviewSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $autoKeys */
        $autoKeys = $options['auto_keys'];

        // The disabled flag depends on the bound section, known only once data is set.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($autoKeys): void {
            $section = $event->getData();
            $isAuto = $section instanceof ManagementReviewSection && \in_array($section->getSectionKey()->value, $autoKeys, true);

            $event->getForm()->add('content', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['rows' => 5],
                'disabled' => $isAuto,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ManagementReviewSection::class, 'auto_keys' => []]);
        $resolver->setAllowedTypes('auto_keys', 'array');
    }
}
