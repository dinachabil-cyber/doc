<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'your@email.com',
                    'autocomplete' => 'email'
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('username', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Choose a username',
                    'autocomplete' => 'username'
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('newPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Leave blank to keep current password',
                    'autocomplete' => 'new-password'
                ],
                'label_attr' => ['class' => 'form-label'],
                'constraints' => [
                    new Length(['min' => 6, 'minMessage' => 'Password must be at least 6 characters']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}