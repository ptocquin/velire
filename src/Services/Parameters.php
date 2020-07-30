<?php
// src/Service/Parameters.php
namespace App\Services;


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;


class Parameters
{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }


    public function getControllerName()
    {
        
        $filesystem = new Filesystem();
        if ($filesystem->exists($this->params->get('app.shared_dir').'/params.yaml')) {
            $values = Yaml::parseFile($this->params->get('app.shared_dir').'/params.yaml');
            $controller_name = $values['controller_name'];
        } else {
            $controller_name = 'test controller name';
        }

        return $controller_name;
    }

    public function getFrequency()
    {
        
        $filesystem = new Filesystem();
        if ($filesystem->exists($this->params->get('app.shared_dir').'/params.yaml')) {
            $values = Yaml::parseFile($this->params->get('app.shared_dir').'/params.yaml');
            $frequency = $values['frequency'];
        } else {
            $frequency = 2500;
        }

        return $frequency;
    }

    public function getPythonCmd()
    {
        return $this->params->get('app.velire_cmd');
    }

    public function getWlanIP()
    {
        $process = new Process("ip -4 -o addr show wlanO | awk '{print $4}' | cut -d/ -f1");
        $process->run();
        $output = $process->getOutput();
        return $output;
    }

    public function getPublicIP()
    {
        $process = new Process("ip -4 -o addr show eth0 | awk '{print $4}' | cut -d/ -f1");
        $process->run();
        $output = $process->getOutput();
        return $output;
    }

    public function getVpnIP()
    {
        $process = new Process("ip -4 -o addr show tun0 | awk '{print $4}' | cut -d/ -f1");
        $process->run();
        $output = $process->getOutput();
        return $output;
    }
}