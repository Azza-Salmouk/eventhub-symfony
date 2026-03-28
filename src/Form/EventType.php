<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Event Title',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'e.g. Tech Summit 2026'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => ['class' => 'form-control', 'rows' => 5, 'placeholder' => 'Describe the event...'],
            ])
            ->add('date', DateTimeType::class, [
                'label'  => 'Date & Time',
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'e.g. Paris, France'],
            ])
            ->add('seats', IntegerType::class, [
                'label' => 'Total Seats',
                'attr'  => ['class' => 'form-control', 'min' => 1, 'placeholder' => '100'],
            ])
            ->add('imageFile', FileType::class, [
                'label'    => 'Event Image (JPG / PNG / WEBP)',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Please upload a valid image (JPG, PNG, WEBP).',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Event::class]);
    }
}
