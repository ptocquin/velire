<?php

namespace App\Controller;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;


use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;


use App\Entity\Pcb;
use App\Entity\Luminaire;
use App\Entity\Channel;
use App\Entity\LuminaireStatus;
use App\Entity\Cluster;
use App\Entity\Led;
use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\Run;
use App\Entity\Log;

use App\Form\RecipeType;
use App\Form\IngredientType;
use App\Form\LuminaireType;
use App\Form\RunType;


class MainController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index(Request $request)
    {
        $today = new \DateTime();
        $cluster_repo = $this->getDoctrine()->getRepository(Cluster::class);
        $log_repo = $this->getDoctrine()->getRepository(Log::class);
        $clusters = $cluster_repo->findAll();
        
        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'clusters' => $clusters,
            'cluster_repo' => $cluster_repo,
            'log_repo' => $log_repo,
            'navtitle' => 'Dashboard',
        ]);
    }

    /**
     * @Route("/cluster/{id}/graph", name="graph")
     */
    public function graph(Request $request, Cluster $cluster)
    {
        $today = new \DateTime();
        $log_repo = $this->getDoctrine()->getRepository(Log::class);
        $logs = $log_repo->getClusterInfo($cluster->getId(), 30);
        
        return $this->render('main/graph.html.twig', [
            'controller_name' => 'MainController',
            'logs' => $logs,
            'cluster' => $cluster,
            'navtitle' => 'Temperature Plot',
        ]);
    }

    /**
     * @Route("/setup/my-lightings", name="my-lightings")
     */
    public function myLightings(Request $request)
    {
    	$all_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();

    	$installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
		$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

		// Compter les clusters existants
		$cluster_number = count($this->getDoctrine()->getRepository(Cluster::class)->findAll());

        $luminaire = new Luminaire;
        $form = $this->createForm(LuminaireType::class, $luminaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($luminaire);
            $em->flush();

            return $this->redirectToRoute('my-lightings');
        }
    	
        return $this->render('setup/my-lightings.html.twig', [
        	'all_luminaires' => $all_luminaires,
        	'clusters' => $clusters,
        	'next_cluster' => $cluster_number+1,
            'form' => $form->createView(),
            'navtitle' => 'My Lightings',
        ]);
    }

    /**
     * @Route("/setup/connected-lightings", name="connected-lightings")
     */
    public function connectedLighting()
    {
        // TODO gestion du status
    	// $installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
        $installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();

		$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

        $empty_clusters = $this->getDoctrine()->getRepository(Cluster::class)->getEmptyClusters();

        $em = $this->getDoctrine()->getManager();
        foreach ($empty_clusters as $cluster) {
            $em->remove($cluster);
        }
        $em->flush();

		// Compter les clusters existants
		$cluster_number = count($this->getDoctrine()->getRepository(Cluster::class)->findAll());
    	
        return $this->render('setup/connected-lightings.html.twig', [
        	'installed_luminaires' => $installed_luminaires,
        	'clusters' => $clusters,
        	'next_cluster' => $cluster_number+1,
            'navtitle' => 'Connected Lightings',
        ]);
    }

    /**
     * @Route("/setup/recipes", name="recipes")
     */
    public function recipes()
    {
        $led_repo = $this->getDoctrine()->getRepository(Led::class);
        $recipe_repo = $this->getDoctrine()->getRepository(Recipe::class);
        $clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();
        $recipes = $recipe_repo->findAll();

        $em = $this->getDoctrine()->getManager();
        
        return $this->render('setup/recipes.html.twig', [
            'clusters' => $clusters,
            'led_repo' => $led_repo,
            'recipe_repo' => $recipe_repo,
            'recipes' => $recipes,
            'navtitle' => 'Recipes',
        ]);
    }

    /**
     * @Route("/setup/recipes/new/{id}", name="new-recipe")
     */
    public function newRecipe(Request $request, Cluster $cluster)
    {
        $em = $this->getDoctrine()->getManager();

        $leds = $this->getDoctrine()->getRepository(Led::class)->getLedTypesFromCluster($cluster);

        $recipe = new Recipe;
        foreach ($leds as $led) {
            $ingredient = new Ingredient;
            $ingredient->setLed($led);
            $em->persist($ingredient);
            $recipe->addIngredient($ingredient);
        }

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($recipe);
            $em->flush();

            return $this->redirectToRoute('recipes');
        }
        
        return $this->render('setup/new-recipes.html.twig', [
            'form' => $form->createView(),
            'navtitle' => 'New Recipe',
        ]);
    }


    /**
     * @Route("/setup/recipes/delete/{id}", name="delete-recipe")
     */
    public function deleteRecipe(Request $request, Recipe $recipe)
    {
        $em = $this->getDoctrine()->getManager();

        foreach ($recipe->getIngredients() as $ingredient) {
            $em->remove($ingredient);
        }

        $em->remove($recipe);
        $em->flush();
        
        return $this->redirectToRoute('recipes');        
    }

    /**
     * @Route("/setup/luminaire/delete/{id}", name="delete-luminaire")
     */
    public function deleteLuminaire(Request $request, Luminaire $luminaire)
    {
        $em = $this->getDoctrine()->getManager();

        foreach ($luminaire->getPcbs() as $pcb) {
            $em->remove($pcb);
        }
        foreach ($luminaire->getChannels() as $channel) {
            $em->remove($channel);
        }

        $em->remove($luminaire);
        $em->flush();
        
        return $this->redirectToRoute('my-lightings');        
    }

    /**
     * @Route("/setup/get-data", name="get-data")
     */
    public function getData()
    {
		$em = $this->getDoctrine()->getManager();
		$session = new Session();

		// Supprimer les luminaires existants
        $luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();
        $clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

        foreach ($clusters as $cluster) {
            $em->remove($cluster);
        }

        $cluster = new Cluster;
        $cluster->setLabel(1);
        $em->persist($cluster);
        
        foreach ($luminaires as $luminaire) {
            $luminaire->setCluster($cluster);
            $em->persist($luminaire);
        }
        
        $em->flush();



		// Interroger le réseau de luminaires
    	$process = new Process('./bin/info.R');
        $process->setTimeout(3600);
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
		    throw new ProcessFailedException($process);
		}

		$output = $process->getOutput();

		// Decode to array
		$data = json_decode($output, true);

        $spots = $data['spots'];

		$i = 0;

		foreach ($spots as $spot) {

			$channels = $spot["channels"];
			$pcbs = $spot["pcb"];
			$status = $spot["status"];

			// Create Luminaire by recusively adding channels / pcbs
			$luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($spot["address"]);

            if(count($luminaire) == 0) {
                // add flash messages
                $session->getFlashBag()->add(
                'info',
                'The lighting '.$spot["address"].' does not exist. Please add it first to your lighting list.'
                );
            return $this->redirectToRoute('connected-lightings'); 
            }

			$luminaire->setSerial($spot["serial"]);
			// // $luminaire->setAddress($spot["address"]);

			// // if ($status["config"] == "OK") {
				$i++;
			// 	$cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel(1);
			// 	if(count($cluster) == 0){
			// 		$cluster = new Cluster;
			// 		$cluster->setLabel(1);
   //                  // $luminaire->setCluster($cluster);
   //                  // $cluster->addLuminaire($luminaire);
			// 		$em->persist($cluster);
			// 		$em->flush();
			// 	} //else {
   //                  $luminaire->setCluster($cluster);
   //             // }
			// // }
            $em->persist($luminaire);
		}

		$em->flush();

		// add flash messages
		$session->getFlashBag()->add(
		    'info',
		    $i.' lightings were detected and successfully installed.'
		);
		return $this->redirectToRoute('connected-lightings');        
    }

    /**
     * @Route("/setup/get-my-lightings", name="get-my-lightings")
     */
    public function getMyLightings()
    {
        $em = $this->getDoctrine()->getManager();
        $session = new Session();

        // Supprimer les luminaires existants
        $luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();
        $clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

        foreach ($luminaires as $l) {
            foreach ($l->getPcbs() as $pcb) {
                $em->remove($pcb);
            }
            foreach ($l->getChannels() as $channel) {
                $em->remove($channel);
            }
         // $em->remove($l);
        }

        foreach ($clusters as $cluster) {
            $em->remove($cluster);
        }

        $cluster = new Cluster;
        $cluster->setLabel(1);
        $em->persist($cluster);
        
        foreach ($luminaires as $luminaire) {
            $luminaire->setCluster($cluster);
            $em->persist($luminaire);
        }
        
        $em->flush();

        // Interroger le réseau de luminaires
        $process = new Process('./bin/info.R');
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();

        // Decode to array
        $data = json_decode($output, true);

        $spots = $data['spots'];

        $i = 0;

        foreach ($spots as $spot) {

            $channels = $spot["channels"];
            $pcbs = $spot["pcb"];
            $status = $spot["status"];

            $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($spot["address"]);
            // $luminaire->setAddress($spot["address"]);

            $luminaire->setSerial($spot["serial"]);

            // if ($status["config"] == "OK") {
                $i++;
            //     $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel(1);
            //     if(count($cluster) == 0){
            //         $cluster = new Cluster;
            //         $cluster->setLabel(1);
            //         $em->persist($cluster);
            //         $em->flush();
            //     }
            //     $luminaire->setCluster($cluster);
            // }

            foreach ($pcbs as $pcb) {
                $p = new Pcb;
                $p->setCrc($pcb["crc"]);
                $p->setSerial($pcb["serial"]);
                $p->setN($pcb["n"]);
                $p->setType($pcb["type"]);

                $em->persist($p);

                $luminaire->addPcb($p);
            }

            $em->persist($luminaire);

            foreach ($channels as $channel) {
                $c = new Channel;
                $c->setChannel($channel["id"]);
                $c->setIPeek($channel["max"]);
                $c->setPcb($channel["address"]);
                $c->setLuminaire($luminaire);
                // $em->persist($c);

                # Vérifie que la Led existe dans la base de données, sinon l'ajoute.
                $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(array(
                    'wavelength' => $channel["wl"],
                    'type' => $channel["type"],
                    'manufacturer' => $channel["manuf"]));

                if ($led == null) {
                    $l = new Led;
                    $l->setWavelength($channel["wl"]);
                    $l->setType($channel["type"]);
                    $l->setManufacturer($channel["manuf"]);
                    $em->persist($l);
                    $em->flush();
                    $c->setLed($l);
                } else {
                    $c->setLed($led);
                }

                $em->persist($c);
                
            }
            

        }

        $em->flush();

        // add flash messages
        $session->getFlashBag()->add(
            'info',
            $i.' lightings were detected and successfully installed.'
        );
        return $this->redirectToRoute('my-lightings');        
    }

    /**
     * @Route("/setup/get-info", name="get-info")
     */
    public function getInfo()
    {
        $em = $this->getDoctrine()->getManager();
        $session = new Session();

        // Interroger le réseau de luminaires
        $process = new Process('./bin/info.R');
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();

        // Decode to array
        $data = json_decode($output, true);

        $spots = $data['spots'];

        $i = 0;

        foreach ($spots as $spot) {

            $channels = $spot["channels"];
            $pcbs = $spot["pcb"];
            $status = $spot["status"];

            $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($spot["address"]);

            foreach ($pcbs as $pcb) {
                $p = $this->getDoctrine()->getRepository(Pcb::class)->findOneBy(array(
                    'serial' => $pcb["serial"],
                    'luminaire' => $luminaire->getId()));
                $n = $p->getN();
                $p->setTemperature($spot["temperature"]["led_pcb_".$n]);
                $em->persist($p);
            }

            foreach ($channels as $channel) {
                $c = $this->getDoctrine()->getRepository(Channel::class)->findOneBy(array(
                    'luminaire' => $luminaire->getId(),
                    'channel' => $channel["id"]
                ));
                $c->setCurrentIntensity($channel["intensity"]);
                $em->persist($c);
            }
        }

        $em->flush();

        return $this->redirectToRoute('home');        
    }

    /**
     * @Route("/setup/add-cluster/{l}/{c}", name="add-cluster")
     */
    public function addCluster(Request $request, $l, $c)
    {
    	$em = $this->getDoctrine()->getManager();
    	$session = new Session();

    	$luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->find($l);
    	$cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($c);
    	// Compter les clusters existants
		$cluster_number = count($this->getDoctrine()->getRepository(Cluster::class)->findAll());
    	if (count($cluster) == 0) {
    		$new_cluster = new Cluster;
    		$new_cluster->setLabel($cluster_number+1);
    		$em->persist($new_cluster);
    		$luminaire->setCluster($new_cluster);
    		$em->persist($luminaire);
    	} else {
			$luminaire->setCluster($cluster);
			$em->persist($luminaire);
    	}
    	
    	$em->flush();

        return $this->redirectToRoute('connected-lightings');
    }

    /**
     * @Route("/setup/clear-clusters", name="clear-clusters")
     */
    public function clearClusters()
    {
    	$em = $this->getDoctrine()->getManager();

    	$luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
    	$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

    	foreach ($clusters as $cluster) {
    		$em->remove($cluster);
    	}

		$cluster = new Cluster;
		$cluster->setLabel(1);
		$em->persist($cluster);
    	
    	foreach ($luminaires as $luminaire) {
    		$luminaire->setCluster($cluster);
    		$em->persist($luminaire);
    	}
    	
    	$em->flush();

        return $this->redirectToRoute('connected-lightings');
    }


    /**
     * @Route("/control/{id}/off", name="set-cluster-off")
     */
    public function setClusterOff(Request $request, Cluster $cluster)
    {
        
        $session = new Session();

        // Interroger le réseau de luminaires
        $process = new Process('./bin/off.R '.$cluster->getId());
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();

        return $this->redirectToRoute('update-log');
    }

    /**
     * @Route("/update/log", name="update-log")
     */
    public function updateLog()
    {
        
        // Interroger le réseau de luminaires
        $process = new Process('./bin/log.R');
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $this->redirectToRoute('home');
    }
}
