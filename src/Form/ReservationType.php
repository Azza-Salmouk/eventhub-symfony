<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label'       => 'Full Name',
                'attr'        => ['class' => 'form-control', 'placeholder' => 'John Doe', 'autocomplete' => 'name'],
                'constraints' => [
                    new NotBlank(message: 'Please enter your full name.'),
                    new Length(min: 2, max: 100),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'you@example.com', 'autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Please enter your email address.'),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone Number',
                'attr'  => ['class' => 'form-control', 'placeholder' => '+1 234 567 890', 'autocomplete' => 'tel'],
                'constraints' => [
                    new NotBlank(message: 'Please enter your phone number.'),
                    new Length(min: 6, max: 20, minMessage: 'Phone number is too short.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Reservation::class]);
    }
}
