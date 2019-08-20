<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;


use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use App\Entity\Luminaire;
use App\Entity\Pcb;
use App\Entity\Channel;
use App\Entity\Led;
use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\Cluster;



/**
 * @Route("/api_old", name="api")
 */
class ApiController extends AbstractController
{
	/**
	 * @Route("/luminaires", name="api-get-luminaires", methods={"GET"})
	 */
    public function getLuminaires()
    {
    	$output = array();
    	$luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();
    	foreach ($luminaires as $luminaire) {
    		$pcbs = $luminaire->getPcbs();
    		$p = array();
    		foreach ($pcbs as $pcb) {
    			$p[] = array(
    				'crc' => $pcb->getCrc(),
    				'serial' => $pcb->getSerial(),
    				'n' => $pcb->getN(),
    				'type' => $pcb->getType()
    			);
    		}
    		$channels = $luminaire->getChannels();
    		$c = array();
    		foreach ($channels as $channel) {
    			$led = $channel->getLed();
    			$c[] = array(
    				'channel' => $channel->getChannel(),
    				'i_peek' => $channel->getIPeek(),
    				'pcb' => $channel->getPcb(),
    				'led' => array(
    					'wavelength' => $led->getWavelength(),
    					'type' => $led->getType(),
    					'manufacturer' => $led->getManufacturer()
    				)
    			);
    		}
    		$output[] = array(
				'serial' => $luminaire->getSerial(), 
				'address' => $luminaire->getAddress(), 
				'pcbs' => $p,
				'channels' => $c
    		);
    	}
    	$response = new JsonResponse($output);
        return $response;
    }

    /**
	 * @Route("/luminaires", name="api-post-luminaires", methods={"POST"})
	 */
    public function addLuminaires(Request $request)
    {
    	$data = json_decode(
            $request->getContent(),
            true
        );

        $em = $this->getDoctrine()->getManager();

        foreach ($data as $l) {	
        	$luminaire = new Luminaire;
        	$luminaire->setAddress($l['address']);
        	$luminaire->setSerial($l['serial']);
			// $em->persist($luminaire);

 			foreach ($l['pcbs'] as $pcb) {
                $p = new Pcb;
                $p->setCrc($pcb["crc"]);
                $p->setSerial($pcb["serial"]);
                $p->setN($pcb["n"]);
                $p->setType($pcb["type"]);

                $em->persist($p);

                $luminaire->addPcb($p);
            }

            $em->persist($luminaire);

            foreach ($l['channels'] as $channel) {
                $c = new Channel;
                $c->setChannel($channel["channel"]);
                $c->setIPeek($channel["i_peek"]);
                $c->setPcb($channel["pcb"]);
                $c->setLuminaire($luminaire);
                // $em->persist($c);

                # Vérifie que la Led existe dans la base de données, sinon l'ajoute.
                $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(array(
                    'wavelength' => $channel['led']["wavelength"],
                    'type' => $channel['led']["type"],
                    'manufacturer' => $channel['led']["manufacturer"]));

                if ($led == null) {
                    $le = new Led;
                    $le->setWavelength($channel["wavelength"]);
                    $le->setType($channel["type"]);
                    $le->setManufacturer($channel["manufacturer"]);
                    $em->persist($le);
                    $em->flush();
                    $c->setLed($le);
                } else {
                    $c->setLed($led);
                }
                $em->persist($c);
            }
        }

        $em->flush();

    	$response = new JsonResponse([
                'status' => 'ok',
            ],
            JsonResponse::HTTP_CREATED);
        return $response;
    }

    /**
     * @Route("/luminaire", name="api-post-luminaire", methods={"POST"})
     */
    public function addLuminaire(Request $request)
    {
        $data = json_decode(
            $request->getContent(),
            true
        );

        $em = $this->getDoctrine()->getManager();

        if(!is_null($this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($data['address']))) {
            $response =  new Response(
                "exists",
                Response::HTTP_OK,
                ['content-type' => 'text/html']
            );

        } else {
            $luminaire = new Luminaire;
            $luminaire->setAddress($data['address']);
            $luminaire->setSerial($data['serial']);

            // On attribue le cluster 1 à ce luminaire (on crée le cluster s'il n'existe pas)
            $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel(1);
            if(is_null($cluster)) {
                $cluster = new Cluster;
                $cluster->setLabel(1);
                $em->persist($cluster);
            }
            $luminaire->setCluster($cluster);            

            if($_SERVER['APP_ENV'] == 'dev') {
                $data = json_decode(file_get_contents($this->get('kernel')->getProjectDir()."/var/test.json"), TRUE);
            } else {
                // réinitialiser + master/slave
                $process = new Process('python3 ./bin/velire-cmd.py --config ./bin/config.yaml --init --quiet');
                $process->setTimeout(3600);
                $process->run();

                // executes after the command finishes
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }

                // Interroger le réseau de luminaires > régénère le fichier de config
                $process = new Process('python3 ./bin/velire-cmd.py --config ./bin/config.yaml --info all --quiet --json --output ../var/config.json');
                $process->setTimeout(3600);
                $process->run();

                // executes after the command finishes
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }

                // Interroger le luminaire ajouté pour insérer les infos dans la base de données
                $process = new Process('python3 ./bin/velire-cmd.py --config ./bin/config.yaml -s '.$data['address'].' --info all --quiet --json');
                //$process = new Process('python3 ./bin/velire-cmd.py -p /dev/ttyUSB0 -s '.$data['address'].' --info all --quiet --json');
                $process->setTimeout(3600);
                $process->run();

                // executes after the command finishes
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }

                $data = json_decode($process->getOutput(), TRUE);

                $l = $data['spots'][0];

                foreach ($l['pcb'] as $pcb) {
                    $p = new Pcb;
                    $p->setCrc($pcb["crc"]);
                    $p->setSerial($pcb["serial"]);
                    $p->setN($pcb["n"]);
                    $p->setType($pcb["type"]);

                    $em->persist($p);

                    $luminaire->addPcb($p);
                }

                $em->persist($luminaire);

                foreach ($l['channels'] as $channel) {
                    $c = new Channel;
                    $c->setChannel($channel["id"]);
                    $c->setIPeek($channel["max"]);
                    // $c->setPcb($channel["pcb"]);
                    $c->setLuminaire($luminaire);
                    // $em->persist($c);

                    # Vérifie que la Led existe dans la base de données, sinon l'ajoute.
                    $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(array(
                        'wavelength' => $channel["wl"],
                        'type' => $channel["type"],
                        'manufacturer' => $channel["manuf"]));

                    if ($led == null) {
                        $le = new Led;
                        $le->setWavelength($channel["wl"]);
                        $le->setType($channel["type"]);
                        $le->setManufacturer($channel["manuf"]);
                        $em->persist($le);
                        $em->flush();
                        $c->setLed($le);
                    } else {
                        $c->setLed($led);
                    }
                    $em->persist($c);
                }
            }

            $em->flush();

            $response =  new Response(
                "created",
                Response::HTTP_CREATED,
                ['content-type' => 'text/html']
            );  
        }
        
        return $response;
    }

    /**
	 * @Route("/recipes", name="api-get-recipes", methods={"GET"})
	 */
    public function getRecipes()
    {
    	$output = array();
    	$recipes = $this->getDoctrine()->getRepository(Recipe::class)->findAll();
    	foreach ($recipes as $recipe) {
    		$ingredients = $recipe->getIngredients();
    		$i = array();
    		foreach ($ingredients as $ingredient) {
    			$led = $ingredient->getLed();
    			$i[] = array(
    				'level' => $ingredient->getLevel(),
    				'led' => array(
    					'wavelength' => $led->getWavelength(),
    					'type' => $led->getType(),
    					'manufacturer' => $led->getManufacturer()
    				)
    			);
    		}
		}
		$output[] = array(
			'label' => $recipe->getLabel(), 
			'description' => $recipe->getDescription(), 
			'ingredients' => $i
		);

    	$response = new JsonResponse($output);
        return $response;
    }

    /**
	 * @Route("/recipes", name="api-add-recipes", methods={"POST"})
	 */
    public function addRecipes(Request $request)
    {
		$data = json_decode(
            $request->getContent(),
            true
        );

        $em = $this->getDoctrine()->getManager();

        foreach ($data as $r) {
        	$recipe = new Recipe;
        	$recipe->setLabel($r['label']);
        	$recipe->setDescription($r['description']);
        	foreach ($r['ingredients'] as $i) {
        		$ingredient = new Ingredient;
        		$ingredient->setLevel($i['level']);

				# Vérifie que la Led existe dans la base de données, sinon l'ajoute.
                $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(array(
                    'wavelength' => $i['led']["wavelength"],
                    'type' => $i['led']["type"],
                    'manufacturer' => $i['led']["manufacturer"]));

                if ($led == null) {
                    $le = new Led;
                    $le->setWavelength($i['led']["wavelength"]);
                    $le->setType($i['led']["type"]);
                    $le->setManufacturer($i['led']["manufacturer"]);
                    $em->persist($le);
                    $em->flush();
                    $ingredient->setLed($le);
                } else {
                    $ingredient->setLed($led);
                }
                $em->persist($ingredient);
                $recipe->addIngredient($ingredient);
                $em->persist($recipe);
        	}
        }
        $em->flush();

    	$response = new JsonResponse([
                'status' => 'ok',
            ],
            JsonResponse::HTTP_CREATED);
        return $response;
    }
}
