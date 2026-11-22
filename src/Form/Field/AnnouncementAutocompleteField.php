<?php

namespace App\Form\Field;

use App\Entity\Announcement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * @extends AbstractType<Announcement>
 */
#[AsEntityAutocompleteField]
final class AnnouncementAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Announcement::class,
            'choice_label' => 'title',
            'searchable_fields' => ['title', 'content'],
            'max_results' => 10,
            'placeholder' => 'app.announcement.search.placeholder',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
