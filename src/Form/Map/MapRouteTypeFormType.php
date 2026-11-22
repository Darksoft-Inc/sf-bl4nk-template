<?php

namespace App\Form\Map;

use App\Entity\MapRouteType;
use App\Form\Support\KeepCurrentData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @extends AbstractType<MapRouteType>
 */
final class MapRouteTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'app.map_route_type.name',
                'constraints' => [
                    new NotBlank(message: 'app.map_route_type.name_not_blank'),
                ],
            ])
            ->add('color', ColorType::class, [
                'label' => 'app.map_route_type.color',
                'empty_data' => KeepCurrentData::emptyData(),
                'constraints' => [
                    new NotBlank(message: 'app.map_route_type.color_not_blank'),
                    new Regex(pattern: '/^#[0-9a-f]{6}$/i', message: 'app.map_route_type.color_invalid'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MapRouteType::class,
        ]);
    }
}
