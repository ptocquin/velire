<?php
// src/Service/Parameters.php
namespace App\Services;


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


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
}