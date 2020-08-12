<?php
// src/Service/Parameters.php
namespace App\Services;


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Luminaire;
use App\Entity\Channel;
use App\Entity\Log;


class Logs
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
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $d = $process->getOutput();

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
}