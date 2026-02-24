<?php

namespace App\Form;

use App\Enum\Permission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserPermissionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (Permission::getGroups() as $groupName => $permissions) {
            $choices = [];
            foreach ($permissions as $permission => $label) {
                $choices[$label] = $permission;
            }

            $builder->add($groupName, ChoiceType::class, [
                'choices' => $choices,
                'multiple' => true,
                'expanded' => true,
                'label' => $groupName,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
