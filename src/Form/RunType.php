<?php

namespace App\Form;

use App\Entity\Run;
use App\Entity\Cluster;
use App\Entity\Program;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;


class RunType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start', null, array(
                'data' => new \DateTime("now"),
                'years' => array(2018, 2019, 2020, 2021),
                'minutes' => array(0,5,10,15,20,25,30,35,40,45,50,55),
                'label' => 'runs.form.start',
            ))
            ->add('label',null, array(
                'label' => 'runs.form.label',
            ))
            ->add('description', null, array(
                'label' => 'runs.form.description',
            ))
            // ->add('cluster', EntityType::class, array(
            //     'class' => Cluster::class,
            //     'choice_label' => function($cluster) {
            //         return $cluster->getLabel()." // ".$cluster->getDescription();
            //     }
            // ))
            ->add('program', EntityType::class, array(
                'class' => Program::class,
                'choice_label' => function($program) {
                    return $program->getLabel()." // ".$program->getDescription();
                },
                'label' => 'runs.form.program',
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Run::class,
        ]);
    }
}
