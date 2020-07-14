<?php

// src/Command/ExampleCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Entity\Luminaire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Services\Parameters;



// 1. Import the ORM EntityManager Interface
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;


class ExampleCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:run-example';
    
    // 2. Expose the EntityManager in the class level
    private $entityManager;
    private $params;

    public function __construct(EntityManagerInterface $entityManager, Parameters $params)
    {
        
        // 3. Update the value of the private entityManager variable through injection
        $this->entityManager = $entityManager;
        $this->params = $params;

        parent::__construct();
    }
    
    protected function configure()
    {
        // ...
    }

    // 4. Use the entity manager in the command code ...
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->entityManager;
        
        // A. Access repositories
        $repo = $em->getRepository(Luminaire::class);
        
        // B. Search using regular methods.
        $res1 = $repo->findOneByAddress(140);
        $output->writeln($res1->getId());
        $process = new Process($this->params->getPythonCmd().' --address 140 --get-info specs --quiet --json');
        $process->setTimeout(3600);
        $process->run();
        $output->writeln($process->getOutput());
        // $res2 = $repo->findBy(['field' => 'value']);
        // $res3 = $repo->findAll();
        // $res4 = $repo->createQueryBuilder('alias')
        //     ->where("alias.field = :fieldValue")
        //     ->setParameter("fieldValue", 123)
        //     ->setMaxResults(10)
        //     ->getQuery()
        //     ->getResult();
        
        // // C. Persist and flush
        // $em->persist($someEntity);
        // $em->flush();
         
        return 0;
    }
}