<?php

namespace App\Form\Map;

use App\Entity\Map;
use App\Entity\MapRoute;
use App\Entity\MapRouteType;
use App\Form\Support\KeepCurrentData;
use App\Map\RoutePoints;
use App\Map\RoutePointsException;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<MapRoute>
 */
final class MapRouteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $map = $options['map'];
        \assert($map instanceof Map);

        $builder
            ->add('type', EntityType::class, [
                'class' => MapRouteType::class,
                'choices' => $options['route_types'],
                'choice_label' => 'name',
                'label' => 'app.map_route.type',
                'empty_data' => KeepCurrentData::emptyData(),
            ])
            ->add('name', TextType::class, [
                'label' => 'app.map_route.name',
                'required' => false,
                'constraints' => [new Length(max: 255, maxMessage: 'app.map_route.name_too_long')],
                'help' => 'app.map_route.name_help',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'app.map_route.description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('isMajor', CheckboxType::class, [
                'label' => 'app.map_route.is_major',
                'required' => false,
            ])
            ->add('points', TextareaType::class, [
                'label' => 'app.map_route.points',
                'mapped' => false,
                'data' => $options['points_text'],
                'attr' => ['rows' => 8, 'placeholder' => '120, 80'],
                'help' => 'app.map_route.points_help',
                'help_translation_parameters' => [
                    '%width%' => $map->getSpaceWidth(),
                    '%height%' => $map->getSpaceHeight(),
                ],
                'constraints' => [new Callback($this->validatePoints(...))],
            ])
        ;
    }

    /**
     * The parse and the validation share the same rules, so a line the form
     * accepts is a line the controller can store unchanged.
     */
    private function validatePoints(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_string($value)) {
            return;
        }

        $form = $context->getRoot();

        if (!$form instanceof FormInterface) {
            return;
        }

        $map = $form->getConfig()->getOption('map');

        if (!$map instanceof Map) {
            return;
        }

        try {
            RoutePoints::parse($value, $map->getSpaceWidth(), $map->getSpaceHeight());
        } catch (RoutePointsException $exception) {
            $context->buildViolation($exception->messageKey)
                ->setParameters($exception->parameters)
                ->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MapRoute::class,
        ]);

        $resolver->setRequired(['route_types', 'points_text', 'map']);
        $resolver->setAllowedTypes('route_types', MapRouteType::class.'[]');
        $resolver->setAllowedTypes('points_text', 'string');
        $resolver->setAllowedTypes('map', Map::class);
    }
}
