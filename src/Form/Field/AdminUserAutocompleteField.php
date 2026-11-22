<?php

namespace App\Form\Field;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * @extends AbstractType<User>
 */
#[AsEntityAutocompleteField]
final class AdminUserAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => User::class,
            'choice_label' => 'email',
            'searchable_fields' => ['email'],
            'max_results' => 10,
            'placeholder' => 'app.admin.user.search.placeholder',
            // The endpoint lives under the bundle's flat /autocomplete
            // namespace, so the role guard is set on the field itself.
            'security' => 'ROLE_ADMIN',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
