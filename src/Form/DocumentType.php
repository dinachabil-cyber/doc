<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Document;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class , [
            'label' => 'Title',
            'attr' => ['placeholder' => 'Document title'],
        ])
            ->add('description', TextareaType::class , [
            'label' => 'Description',
            'required' => false,
            'attr' => ['rows' => 3, 'placeholder' => 'Optional description'],
        ])
            ->add('category', EntityType::class , [
            'class' => Category::class ,
            'choice_label' => 'name',
            'required' => false,
            'placeholder' => '-- Select a category --',
        ])
            ->add('file', FileType::class , [
            'label' => 'File (PDF, image, etc.)',
            'mapped' => true,
            'required' => $options['is_new'],
            'constraints' => [
                new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'text/plain',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid document (PDF, Word, Excel, image, or text file).',
                ]),
            ],
        ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class ,
            'is_new' => true,
        ]);
    }
}
