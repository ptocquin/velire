<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

/**
 * Description of wigExtTension
 *
 * @author Pierre Tocquin
 */

class AppExtension extends AbstractExtension
{

    public function getTests()
    {
        return [
            new TwigTest('file_exists', [$this, 'fileExists']),
        ];
    }

  public function fileExists($file)
  {
    return file_exists($file);
  }

}