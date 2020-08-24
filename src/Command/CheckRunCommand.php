<?php

// src/Command/ExampleCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Entity\Luminaire;
use App\Entity\Run;
use App\Entity\RunStep;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Services\Parameters;



// 1. Import the ORM EntityManager Interface
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;


class CheckRunCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:check-run';
    
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

        $time = date('Y-m-d H:i:00');
        // $time = date('2020-07-10 17:42:00');
        $output->writeln($time);
        $luminaire_repo = $em->getRepository(Luminaire::class);
        $run_repo = $em->getRepository(Run::class);
        $step_repo = $em->getRepository(RunStep::class);

        // Gestion des runs terminés
        $runs = $run_repo->getPastRuns($time);

        foreach ($runs as $run) {
            $run->setStatus('past');
            $steps = $run->getSteps();
            foreach ($steps as $step) {
                $em->remove($step);
            }
            $em->flush();
            $output->writeln('Run '.$run->getId().' ended (status > past)');
        }

        // On vérifie si on doit lancer une commande !NOW
        $steps = $step_repo->findStepToRun($time);
        $output->writeln(count($steps). ' steps to execute');
        foreach ($steps as $step) {
            $output->writeln('Command: '.$step->getCommand());
            $process = new Process($step->getCommand());
            $process->setTimeout(3600);

            $NUM_OF_ATTEMPTS = 5;
            $attempts = 0;

            do {
                try
                {
                    $process->run();
                } catch (Exception $e) {
                    $attempts++;
                    sleep(1);
                    continue;
                }
                break;
            } while($attempts < $NUM_OF_ATTEMPTS);

            if (!$process->isSuccessful()) {
                $msg = $error_msg;
                $output->writeln('The command failed Command: '.$process->getOutput());
                // $output = '';
            } else {
                $msg = $success_msg;
                // $output = $process->getOutput();
                // les anciennes commandes reçoivent le status 2; seule la dernière
                // commande exécutée garde le status 1
                // les commandes anciennes non exécutées (par exemple car le programme 
                // a été lancé après le start de certaines commandes) reçoivent le status 3
                // SQL="$SQL UPDATE run_step SET status=2 WHERE run_id=${RUN_ID} AND status=1;"
                // SQL="$SQL UPDATE run_step SET status=1 WHERE id=$ID;"
                // SQL="$SQL UPDATE run_step SET status=3 WHERE run_id=${RUN_ID} AND start < '$TIME' AND status=0;"
                foreach ($step_repo->getLastSteps($step->getRun()) as $s) {
                    $s->setStatus('2');
                    $output->writeln('Step '.$s->getId().' set status to 2');
                    $em->flush();
                }
                $step->setStatus('1');
                $output->writeln('Current step '.$step->getId().' set status to 1');
                $em->flush();
                foreach ($step_repo->getNonExecutedSteps($step->getRun(), $time) as $s) {
                    $s->setStatus('3');
                    $output->writeln('Previous step '.$s->getId().' set status to 3');
                    $em->flush();
                }
            }   
        }

        // On vérifie que des commandes antérieures n'ont pas été lancées
        // Quels sont les runs qui ont des steps antérieurs avec status 0
        $runs = $run_repo->getRunningRuns($time);
        foreach ($runs as $run) {
            foreach ($step_repo->getLastNonExecutedStep($run, $time) as $step) {
                $output->writeln('The non executed step '.$step->getId().' is executed now !');
                $output->writeln('Command: '.$step->getCommand());
                $process = new Process($step->getCommand());
                $process->setTimeout(3600);

                $attempts = 0;

                do {
                    try
                    {
                        $process->run();
                    } catch (Exception $e) {
                        $attempts++;
                        sleep(1);
                        continue;
                    }
                    break;
                } while($attempts < $NUM_OF_ATTEMPTS);

                // executes after the command finishes
                if (!$process->isSuccessful()) {
                    $output->writeln('The command failed Command: '.$process->getOutput());
                } else {
                    // les anciennes commandes reçoivent le status 2; seule la dernière
                    // commande exécutée garde le status 1
                    // les commandes anciennes non exécutées (par exemple car le programme 
                    // a été lancé après le start de certaines commandes) reçoivent le status 3
                    // SQL="$SQL UPDATE run_step SET status=2 WHERE run_id=${RUN_ID} AND status=1;"
                    // SQL="$SQL UPDATE run_step SET status=1 WHERE id=$ID;"
                    // SQL="$SQL UPDATE run_step SET status=3 WHERE run_id=${RUN_ID} AND start < '$TIME' AND status=0;"
                    foreach ($step_repo->getLastSteps($step->getRun()) as $s) {
                        $s->setStatus('2');
                        $output->writeln('Step '.$s->getId().' set status to 2');
                        $em->flush();
                    }
                    $step->setStatus('1');
                    $em->flush();
                    $output->writeln('Current step '.$step->getId().' set status to 1');
                    foreach ($step_repo->getNonExecutedSteps($step->getRun(), $time) as $s) {
                        $s->setStatus('3');
                        $em->flush();
                        $output->writeln('Previous step '.$s->getId().' set status to 3');
                    }
                }
            }
        }
        

        $em->flush();
         
        return 0;
    }
}