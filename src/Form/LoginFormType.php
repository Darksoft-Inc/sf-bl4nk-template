<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 *
 * Renders the login form with the field names the form_login firewall
 * expects. The type has no block prefix, so the children submit as
 * `_username`, `_password`, and `_remember_me`. The fields stay unmapped:
 * the firewall authenticates the request before this form is read, and the
 * template keeps the `_csrf_token` field the firewall validates itself.
 */
final class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', EmailType::class, [
                'label' => 'app.account.email',
                'mapped' => false,
                'attr' => ['autocomplete' => 'email', 'autofocus' => 'autofocus'],
            ])
            ->add('_password', PasswordType::class, [
                'label' => 'app.account.password',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
            ])
            ->add('_remember_me', CheckboxType::class, [
                'label' => 'app.login.remember_me',
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The firewall validates the `_csrf_token` field with the
        // `authenticate` token id; a second form-level CSRF check would
        // validate the same token twice.
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
