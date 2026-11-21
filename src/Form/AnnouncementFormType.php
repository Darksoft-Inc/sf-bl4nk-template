<?php

namespace App\Form;

use App\Entity\Announcement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Announcement>
 */
final class AnnouncementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'app.announcement.title',
                'constraints' => [new NotBlank(message: 'app.announcement.title_not_blank')],
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'app.announcement.published_at',
                'widget' => 'single_text',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'app.announcement.content',
                'constraints' => [new NotBlank(message: 'app.announcement.content_not_blank')],
                'attr' => ['rows' => 10],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Announcement::class,
        ]);
    }
}
