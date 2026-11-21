<?php

namespace App\Form;

use App\Entity\MapPoint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\UX\Icons\Exception\IconNotFoundException;
use Symfony\UX\Icons\IconRendererInterface;

/**
 * @extends AbstractType<MapPoint>
 */
final class MapPointFormType extends AbstractType
{
    public function __construct(
        private readonly IconRendererInterface $iconRenderer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $spaceWidth = $options['space_width'];
        $spaceHeight = $options['space_height'];
        \assert(\is_int($spaceWidth) && \is_int($spaceHeight));

        $builder
            ->add('title', TextType::class, [
                'label' => 'app.map_point.title',
                'constraints' => [
                    new NotBlank(message: 'app.map_point.title_not_blank'),
                    new Length(max: 255, maxMessage: 'app.map_point.title_too_long'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'app.map_point.description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('x', NumberType::class, [
                'label' => 'app.map_point.x',
                'html5' => true,
                'scale' => 1,
                'empty_data' => KeepCurrentData::emptyData(),
                'help' => 'app.map_point.x_help',
                'help_translation_parameters' => ['%space%' => $spaceWidth],
                'constraints' => [new Range(min: 0, max: $spaceWidth, notInRangeMessage: 'app.map_point.out_of_space')],
            ])
            ->add('y', NumberType::class, [
                'label' => 'app.map_point.y',
                'html5' => true,
                'scale' => 1,
                'empty_data' => KeepCurrentData::emptyData(),
                'help' => 'app.map_point.y_help',
                'help_translation_parameters' => ['%space%' => $spaceHeight],
                'constraints' => [new Range(min: 0, max: $spaceHeight, notInRangeMessage: 'app.map_point.out_of_space')],
            ])
            ->add('symbols', TextType::class, [
                'label' => 'app.map_point.symbols',
                'mapped' => false,
                'required' => false,
                'data' => $options['symbols_text'],
                'help' => 'app.map_point.symbols_help',
                'constraints' => [new Callback($this->validateSymbols(...))],
            ])
        ;
    }

    /**
     * Each symbol name must be a renderable ux-icons icon: the public page
     * renders the stored names without a second check, and an unknown icon
     * would fail the rendering there.
     */
    private function validateSymbols(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_string($value)) {
            return;
        }

        foreach (Symbols::parse($value) as $name) {
            try {
                $this->iconRenderer->renderIcon($name);
            } catch (IconNotFoundException) {
                $context->buildViolation('app.map_point.symbol_not_found')
                    ->setParameter('%name%', $name)
                    ->addViolation();
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MapPoint::class,
        ]);

        $resolver->setRequired(['space_width', 'space_height', 'symbols_text']);
        $resolver->setAllowedTypes('space_width', 'int');
        $resolver->setAllowedTypes('space_height', 'int');
        $resolver->setAllowedTypes('symbols_text', 'string');
    }
}
