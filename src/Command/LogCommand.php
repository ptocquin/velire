<?php

// src/Command/ExampleCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Entity\Luminaire;
use App\Entity\Run;
use App\Entity\RunStep;
use App\Entity\Log;
use App\Entity\Channel;


use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Services\Parameters;



// 1. Import the ORM EntityManager Interface
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class LogCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:log';
    
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
        $NUM_OF_ATTEMPTS = 5;
        $SLEEP = 10;

        $time = date('Y-m-d H:i:00');
        // $time = date('2020-07-10 17:42:00');
        $output->writeln($time);
        $luminaire_repo = $em->getRepository(Luminaire::class);
        $run_repo = $em->getRepository(Run::class);
        $step_repo = $em->getRepository(RunStep::class);
        $log_repo = $em->getRepository(Log::class);
        $channel_repo = $em->getRepository(Channel::class);
        
        // Interroger le réseau de luminaires
        $luminaires = $luminaire_repo->findAll();
        $opt = "";
        foreach ($luminaires as $l) {
            $opt .= $l->getAddress().' ';
        }

        $cmd = $this->params->getPythonCmd(). ' -a '.$opt.' --get-info --json';

        $output->writeln($cmd);

        $process = new Process($cmd);
        $process->setTimeout(3600);

        $attempts = 0;

        do {
            try {
                    $output->writeln('Attempt: '.$attempts);
                    $process->mustRun();
                } catch (ProcessFailedException $exception) {
                    $msg = $exception->getMessage();
                    $output->writeln('The command failed with msg '.$msg);
                    $attempts++;
                    sleep($SLEEP);
                    continue;
                }
            // try
            // {
            //     $process->run();
            // } catch (Exception $e) {
            //     $attempts++;
            //     sleep(1);
            //     continue;
            // }
            break;
        } while($attempts < $NUM_OF_ATTEMPTS);


        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $d = $process->getOutput();

        $output->writeln($d);

        $data = json_decode($d, true);

        foreach ($data['channels_config'] as $lighting => $channels) {
            $luminaire = $luminaire_repo->findOneByAddress($lighting);
            $cluster = $luminaire->getCluster();
            $channels_on = array();
            foreach ($channels as $channel => $values) {
                $c = $channel_repo->findOneByChannelAndLuminaire($channel, $luminaire);
                if(!is_null($c) && $values['intensity'] > 0) {
                    $channels_on[] = array('color' => $c->getLed()->getType().'_'.$c->getLed()->getWavelength(), 'intensity' => $values['intensity']);
                }
            }
            $luminaire_info = array(
                'address' => $lighting, 
                'serial' => $luminaire->getSerial(), 
                'led_pcb_0' => $data['temp'][$lighting]['pcb-led']['0'], 
                'led_pcb_1' => $data['temp'][$lighting]['pcb-led']['1'],
                'channels_on' => $channels_on
            );
            // $output->writeln($luminaire_info);

            $log = new Log();
            $log->setTime(new \DateTime);
            $log->setType("luminaire_info");
            $log->setLuminaire($luminaire);
            $log->setCluster($luminaire->getCluster());
            $log->setValue($luminaire_info);

            $em->persist($log);
        }

        $em->flush();
         
        return 0;
    }
}