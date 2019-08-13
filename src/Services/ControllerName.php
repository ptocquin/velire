<?php
// src/Service/ControllerName.php
namespace App\Services;


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

class ControllerName
{
    public function getControllerName()
    {
        
        $filesystem = new Filesystem();
        if ($filesystem->exists("params.yaml")) {
            $values = Yaml::parseFile('params.yaml');
            $controller_name = $values['controller_name'];
        } else {
            $controller_name = 'test controller name';
        }

        return $controller_name;
    }
}