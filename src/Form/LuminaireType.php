<?php

namespace App\Form;

use App\Entity\Luminaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LuminaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // ->add('serial')
            ->add('address', null, array(
                'label' => 'lightings.form.new.address'
            ))
            // ->add('status')
            // ->add('cluster')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Luminaire::class,
        ]);
    }
}
