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
        } else {
            $values = array(
                'controller_name' => 'test controller name'
            );
        }

        return $values['controller_name'];
    }
}