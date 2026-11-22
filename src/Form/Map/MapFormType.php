<?php

namespace App\Form\Map;

use App\Entity\Map;
use App\Form\Support\KeepCurrentData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<Map>
 */
final class MapFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $imageFiles */
        $imageFiles = $options['image_files'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'app.map.name',
                'constraints' => [
                    new NotBlank(message: 'app.map.name_not_blank'),
                    new Length(max: 255, maxMessage: 'app.map.name_too_long'),
                ],
            ])
            ->add('image', ChoiceType::class, [
                'label' => 'app.admin.map.image',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'app.admin.map.image_none',
                'data' => $options['current_image'],
                'choices' => array_combine($imageFiles, $imageFiles),
                'help' => 'app.admin.map.image_help',
            ])
            ->add('minZoom', IntegerType::class, [
                'label' => 'app.admin.map.min_zoom',
                'empty_data' => KeepCurrentData::emptyData(),
                'constraints' => [new Range(min: 0, minMessage: 'app.admin.map.zoom_not_negative')],
            ])
            ->add('maxZoom', IntegerType::class, [
                'label' => 'app.admin.map.max_zoom',
                'empty_data' => KeepCurrentData::emptyData(),
                'constraints' => [
                    new Range(min: 0, minMessage: 'app.admin.map.zoom_not_negative'),
                    new Callback($this->validateMaxZoom(...)),
                ],
            ])
        ;
    }

    /**
     * The zoom range is a rule of the pair, so it lives as a constraint on
     * the max field: the violation attaches there and reads the model value
     * of the min field, which the form has already written during submit.
     */
    private function validateMaxZoom(mixed $value, ExecutionContextInterface $context): void
    {
        $form = $context->getRoot();
        $map = $form instanceof FormInterface ? $form->getData() : null;

        if ($map instanceof Map && \is_int($value) && $map->getMinZoom() > $value) {
            $context->buildViolation('app.map.max_zoom_not_below_min')->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Map::class,
        ]);

        $resolver->setRequired(['image_files', 'current_image']);
        $resolver->setAllowedTypes('image_files', 'string[]');
        $resolver->setAllowedTypes('current_image', ['null', 'string']);
    }
}
