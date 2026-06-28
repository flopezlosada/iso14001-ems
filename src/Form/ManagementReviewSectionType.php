<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManagementReviewSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One section of a management review, with three shapes driven by the bound section:
 *  - auto-generated input (has a provider, listed in `auto_keys`): its text is disabled, review only,
 *    so a tampered POST cannot overwrite the snapshot;
 *  - output decision (has {@see \App\Enum\ReviewSectionKey::decisionOptions()}): a closed verdict
 *    dropdown plus a detail text;
 *  - manual input: a plain editable text.
 * The section's heading is rendered from the entity in the template.
 *
 * @extends AbstractType<ManagementReviewSection>
 */
class ManagementReviewSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $autoKeys */
        $autoKeys = $options['auto_keys'];

        // The fields depend on the bound section, known only once data is set.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($autoKeys): void {
            $section = $event->getData();
            $key = $section instanceof ManagementReviewSection ? $section->getSectionKey() : null;
            /** @var list<string> $verdicts */
            $verdicts = null !== $key ? $key->decisionOptions() : [];
            $isAuto = null !== $key && \in_array($key->value, $autoKeys, true);
            $form = $event->getForm();

            if ([] !== $verdicts) {
                $form->add('decision', ChoiceType::class, [
                    'label' => 'Valoración',
                    'choices' => array_combine($verdicts, $verdicts),
                    'placeholder' => 'Sin valorar',
                    'required' => false,
                ]);
            }

            $form->add('content', TextareaType::class, [
                'label' => [] !== $verdicts ? 'Detalle' : false,
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
