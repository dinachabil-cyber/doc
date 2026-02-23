<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class , [
            'label' => 'Name',
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Enter category name',
            ],
        ])
            ->add('slug', TextType::class , [
            'label' => 'Slug',
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'category-slug',
            ],
            'required' => false,
            'help' => 'Optional: leave empty to auto-generate from name',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class ,
        ]);
    }
}
