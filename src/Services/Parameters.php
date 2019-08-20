<?php
// src/Service/Parameters.php
namespace App\Services;


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

class Parameters
{
    public function getControllerName()
    {
        
        $filesystem = new Filesystem();
        if ($filesystem->exists("../var/params.yaml")) {
            $values = Yaml::parseFile('../var/params.yaml');
            $controller_name = $values['controller_name'];
        } else {
            $controller_name = 'test controller name';
        }

        return $controller_name;
    }
}