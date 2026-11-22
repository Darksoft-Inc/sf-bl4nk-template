<?php

namespace App\Form\Account;

use App\Entity\User;
use App\Form\Support\PasswordConstraints;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function __construct(
        private readonly PasswordConstraints $passwordConstraints,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', options: [
                'label' => 'app.account.email',
                // EmailType sets the HTML5 input type only, so the server-side check is missing without this.
                'constraints' => [new Email()],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'app.form.agree_terms',
                // The label holds an anchor to the placeholder terms page,
                // so it renders as HTML with the URL from the router.
                'label_html' => true,
                'label_translation_parameters' => [
                    '%terms_url%' => $this->router->generate('app_documentation_show', ['page' => 'terms']),
                ],
                'constraints' => [
                    new IsTrue(
                        message: 'app.registration.agree_terms',
                    ),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => $this->passwordConstraints->forNewPassword(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
