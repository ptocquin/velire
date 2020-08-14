<?php
// src/Services/Utils.php
namespace App\Services;


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

use App\Entity\Luminaire;
use App\Entity\Channel;
use App\Entity\Log;
use App\Entity\RunStep;


class Lumiatec
{
    private $params;
    private $em;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->em = $em;
    }


    public function updateLogs()
    {

        // $time = date('Y-m-d H:i:00');
        // $time = date('2020-07-10 17:42:00');
        $luminaire_repo = $this->em->getRepository(Luminaire::class);
        $channel_repo = $this->em->getRepository(Channel::class);
        
        // Interroger le réseau de luminaires
        $luminaires = $luminaire_repo->findAll();
        $opt = "";
        foreach ($luminaires as $l) {
            $opt = $l->getAddress().' ';
        }

        $cmd = $this->params->get('app.velire_cmd'). ' -a '.$opt.' --get-info --json';
        $process = new Process($cmd);
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
            $output = '';
        } else {
            $msg = $success_msg;
            $output = $process->getOutput();
        }

        $d = $output;

        $data = json_decode($d, true);

        foreach ($data['channels_config'] as $lighting => $channels) {
            $luminaire = $luminaire_repo->findOneByAddress($lighting);
            $cluster = $luminaire->getCluster();
            $channels_on = array();
            foreach ($channels as $channel => $values) {
                // dd('channel '.$channel.' / lighting '.$lighting);
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

            $log = new Log();
            $log->setTime(new \DateTime);
            $log->setType("luminaire_info");
            $log->setLuminaire($luminaire);
            $log->setCluster($luminaire->getCluster());
            $log->setValue($luminaire_info);

            $this->em->persist($log);
        }

        $this->em->flush();
    }

    public function sendCmd($args, $success_msg="", $error_msg="")
    {
            $session = new Session;

            $cmd = $this->params->get('app.velire_cmd').$args;
            $process = new Process($cmd);

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
                $output = '';
            } else {
                $msg = $success_msg;
                $output = $process->getOutput();
            }

            return array('message' => $msg, 'output' => $output);
    }

    public function setRun($run)
    {
        # Fetch lightings addresses
        $luminaires = $run->getCluster()->getLuminaires();
        $list = " --address ";
        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
        }

        # Fetch Steps
        $program = $run->getProgram();
        $steps = $program->getSteps();
        # Start
        $start = $run->getStart();
        $goto = -1;
        $step_index = 0;

        while ($step_index < count($steps)) {
            // die(print_r($steps[$step_index]->getType()));
            $step = $steps[$step_index];
            $type = $step->getType();

            switch ($type) {
                case "time":
                    list($hours, $minutes) = explode(':', $step->getValue(), 2);
                    $step_duration = $minutes * 60 + $hours * 3600;
                    $commands = [];
                    $frequency = $step->getRecipe()->getFrequency();
                    if(is_null($frequency)) {
                        // default frequency
                        $filesystem = new Filesystem();
                        if ($filesystem->exists($this->params->get('app.shared_dir').'/params.yaml')) {
                            $values = Yaml::parseFile($this->params->get('app.shared_dir').'/params.yaml');
                            $frequency = $values['frequency'];
                        } else {
                            $frequency = 2500;
                        }
                    }
                    $ingredients = $step->getRecipe()->getIngredients();
                    foreach ($ingredients as $ingredient) {
                        $level = $ingredient->getLevel();
                        $led = $ingredient->getLed();
                        $color = $led->getType()."_".$led->getWavelength();
                        $commands[] = $color." ".$level." ".$ingredient->getPwmStart()." ".$ingredient->getPwmStop();
                    }
                    $cmd = $this->params->get('app.velire_cmd').$list.' --exclusive --set-power 1 --set-freq '.$frequency.' --set-colors '.implode(" ", $commands);
                    
                    $new_step = new RunStep();
                    $new_step->setRun($run);
                    $new_step->setStart($start);
                    $new_step->setCommand($cmd);
                    $new_step->setStatus(0);
                    $this->em->persist($new_step);
                    $this->em->flush();
                    $start = $start->add(new \DateInterval('PT'.$step_duration.'S'));
                    $step_index = $step_index + 1;
                    break;
                case "off":
                    list($hours, $minutes) = explode(':', $step->getValue(), 2);
                    $step_duration = $minutes * 60 + $hours * 3600;
                    $cmd = $this->params->get('app.velire_cmd').$list." --shutdown";
                    
                    $new_step = new RunStep();
                    $new_step->setRun($run);
                    $new_step->setStart($start);
                    $new_step->setCommand($cmd);
                    $new_step->setStatus(0);
                    $this->em->persist($new_step);
                    $this->em->flush();
                    $start = $start->add(new \DateInterval('PT'.$step_duration.'S'));
                    $step_index = $step_index + 1;
                    break;
                case "goto":
                    list($s, $n) = explode(':', $step->getValue(), 2);
                    if($goto < 0){
                        $goto = $n;
                    } elseif ($goto == 0) {
                        $goto = -1;
                        $step_index = $step_index + 1;
                    } elseif ($goto > 0) {
                        $step_index = $s;
                        $goto = $goto - 1;
                    }
                    break;
            }
        }

        $run->setDateEnd($start);
        // $this->em->persist($run);
        $this->em->flush(); 
    }
}