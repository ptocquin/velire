<?php

namespace App\Form;

use App\Entity\Run;
use App\Entity\Cluster;
use App\Entity\Program;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;


class RunEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start')
            ->add('label')
            ->add('description')
            // ->add('cluster', EntityType::class, array(
            //     'class' => Cluster::class,
            //     'choice_label' => function($cluster) {
            //         return $cluster->getLabel()." // ".$cluster->getDescription();
            //     }
            // ))
            ->add('program')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Run::class,
        ]);
    }
}
