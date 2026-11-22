<?php

namespace App\Form\Map;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractType<mixed>
 */
final class MapImageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('image', DropzoneType::class, [
                'label' => 'app.admin.map_images.file',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(
                        // Matches the php.ini upload_max_filesize of the
                        // current platform. Larger images take the
                        // pre-existing path: an operator drops the file
                        // into the map image directory.
                        maxSize: '2M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'app.map_images.mime_types',
                        maxSizeMessage: 'app.map_images.max_size',
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
