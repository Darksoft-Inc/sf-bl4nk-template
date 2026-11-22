<?php

namespace App\Form\Announcement;

use App\Form\Field\AnnouncementAutocompleteField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
final class AdminAnnouncementSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Reuses the public announcement autocompleter: its endpoint serves
        // public data, so the admin form needs no field of its own.
        $builder
            ->add('announcement', AnnouncementAutocompleteField::class, [
                'label' => 'app.admin.announcement.search.label',
                'required' => false,
                'placeholder' => 'app.admin.announcement.search.placeholder',
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
