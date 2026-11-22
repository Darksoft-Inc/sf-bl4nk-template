<?php

namespace App\Form\User;

use App\Form\Field\AdminUserAutocompleteField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
final class AdminUserSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', AdminUserAutocompleteField::class, [
                'label' => 'app.admin.user.search.label',
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
