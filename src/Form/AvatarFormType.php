<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractType<mixed>
 */
final class AvatarFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('avatar', DropzoneType::class, [
                'label' => 'app.account.avatar',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'app.avatar.mime_types',
                        maxSizeMessage: 'app.avatar.max_size',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
