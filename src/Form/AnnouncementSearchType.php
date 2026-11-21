<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
final class AnnouncementSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('announcement', AnnouncementAutocompleteField::class, [
                'label' => 'app.announcement.search.label',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // A search form changes nothing, so it submits as GET without CSRF.
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
