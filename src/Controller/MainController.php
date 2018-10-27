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

use App\Entity\Pcb;
use App\Entity\Luminaire;
use App\Entity\Channel;
use App\Entity\LuminaireStatus;
use App\Entity\Cluster;
use App\Entity\Led;
use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\Run;


use App\Form\RecipeType;
use App\Form\IngredientType;
use App\Form\LuminaireType;

class MainController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index()
    {
        $today = new \DateTime();

        $runs = $this->getDoctrine()->getRepository(Run::class)->getRunningRuns($today);
        
        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'runs' => $runs,
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
        ]);
    }

    /**
     * @Route("/setup/connected-lightings", name="connected-lightings")
     */
    public function connectedLighting()
    {
    	$installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
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
        	'next_cluster' => $cluster_number+1
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
     * @Route("/setup/get-data", name="get-data")
     */
    public function getData()
    {
		$em = $this->getDoctrine()->getManager();
		$session = new Session();

		// Supprimer les luminaires existants
		$old_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();
		// Compter les clusters existants
		$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

		// foreach ($old_luminaires as $ol) {
		// 	foreach ($ol->getStatus() as $status) {
		// 		$status->removeLuminaire($ol);
		// 		$em->persist($status);
		// 		$em->flush();
		// 	}
		// 	$em->remove($ol);
		// }

		foreach ($clusters as $c) {
			$em->remove($c);
		}
		$em->flush();

		// Interroger le réseau de luminaires
    	$process = new Process('./bin/info.R');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
		    throw new ProcessFailedException($process);
		}

		$output = $process->getOutput();

        die(print_r($output));

		// Decode to array
		$data = json_decode($output, true);
        $spots = $data['spots'];

		$i = 0;

		foreach ($spots as $spot) {

			$channels = $spot["channels"];
			$pcbs = $spot["pcb"];
			$status = $spot["status"];

			// Create Luminaire by recusively adding channels / pcbs
			$luminaire = new Luminaire;
			$luminaire->setSerial($spot["serial"]);
			$luminaire->setAddress($spot["address"]);

			if ($status["config"] == "OK") {
				$i++;
				$cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel(1);
				if(count($cluster) == 0){
					$cluster = new Cluster;
					$cluster->setLabel(1);
					$em->persist($cluster);
					$em->flush();
				}
				$luminaire->setCluster($cluster);
			}

			// foreach ($status as $st) {
			// 	$s = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findOneByCode($st["code"]);
			// 	$luminaire->addStatus($s);
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
                $em->persist($c);

                # Vérifie que la Led existe dans la base de données, sinon l'ajoute.
                $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(array(
                    'wavelength' => $channel["wl"],
                    'type' => $channel["type"],
                    'manufacturer' => $channel["manuf"]));

                // die(var_dump(count($led)));

                if ($led == null) {
                    $l = new Led;
                    $l->setWavelength($channel["wl"]);
                    $l->setType($channel["type"]);
                    $l->setManufacturer($channel["manuf"]);
                    $l->addChannel($c);
                    $em->persist($l);
                    $em->flush();
                } else {
                    $c->setLed($led);
                }
	
			}

		}

		$em->flush();

		$installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();

		// add flash messages
		$session->getFlashBag()->add(
		    'info',
		    $i.' lightings were detected and successfully installed.'
		);
		return $this->redirectToRoute('connected-lightings');        
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
     * @Route("/control/by-color", name="control-by-color")
     */
    public function controlByColor()
    {
    	$em = $this->getDoctrine()->getManager();

    	$luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
    	$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

        return $this->render('control/control-by-color.html.twig', [
        	'clusters' => $clusters,
        ]);

        return $this->redirectToRoute('control-by-color');
    }

    /**
     * @Route("/control/by-color/{id}", name="control-by-color-id")
     */
    public function controlByColorId(Request $request, $id)
    {
    	$em = $this->getDoctrine()->getManager();

    	$luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
    	$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();
    	$current_cluster = $this->getDoctrine()->getRepository(Cluster::class)->find($id);

        return $this->render('control/control-by-color.html.twig', [
        	'clusters' => $clusters,
        	'current_cluster' => $current_cluster
       	]);

    }
}
