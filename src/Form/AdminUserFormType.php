<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;

/**
 * @extends AbstractType<User>
 */
final class AdminUserFormType extends AbstractType
{
    /**
     * The roles an admin can grant. ROLE_USER is not listed because
     * User::getRoles() guarantees it for every account.
     *
     * @return array<string, string> Translation key => role
     */
    public static function assignableRoles(): array
    {
        return ['app.admin.user.administrator' => 'ROLE_ADMIN'];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'app.account.email',
                'constraints' => [new Email()],
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'app.account.verified',
                'required' => false,
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'app.admin.user.roles',
                'choices' => self::assignableRoles(),
                'multiple' => true,
                'required' => false,
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
