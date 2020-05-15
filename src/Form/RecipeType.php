<?php

namespace App\Form;

use App\Entity\Recipe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('label', null, array(
                'label' => 'recipes.form.new.label'
            ))
            ->add('description', null, array(
                'label' => 'recipes.form.new.description'
            ))
            ->add('color', ColorType::class, array(
                'label' => 'recipes.form.new.color'
            ))
            ->add('ingredients', CollectionType::class, array(
                'entry_type' => IngredientType::class,
                'entry_options' => array('label' => false),
                'label' => 'recipes.form.new.ingredients'
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
