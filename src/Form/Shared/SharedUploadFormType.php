<?php

namespace App\Form\Shared;

use App\Uploads\SharedUploadStorage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractType<mixed>
 */
final class SharedUploadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', DropzoneType::class, [
                'label' => 'app.shared.file',
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: SharedUploadStorage::MAX_SIZE,
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'text/plain'],
                        mimeTypesMessage: 'app.shared.mime_types',
                        maxSizeMessage: 'app.shared.max_size',
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
