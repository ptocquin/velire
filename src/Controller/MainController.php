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


class MainController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index()
    {
        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
        ]);
    }

    /**
     * @Route("/setup/my-lightings", name="my-lightings")
     */
    public function myLightings()
    {
    	$all_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();

    	$installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
		$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

		// Compter les clusters existants
		$cluster_number = count($this->getDoctrine()->getRepository(Cluster::class)->findAll());
    	
        return $this->render('setup/my-lightings.html.twig', [
        	'all_luminaires' => $all_luminaires,
        	'clusters' => $clusters,
        	'next_cluster' => $cluster_number+1
        ]);
    }

    /**
     * @Route("/setup/connected-lightings", name="connected-lightings")
     */
    public function connectedLighting()
    {
    	$installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findInstalledLuminaire();
		$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

		// Compter les clusters existants
		$cluster_number = count($this->getDoctrine()->getRepository(Cluster::class)->findAll());
    	
        return $this->render('setup/connected-lightings.html.twig', [
        	'installed_luminaires' => $installed_luminaires,
        	'clusters' => $clusters,
        	'next_cluster' => $cluster_number+1
        ]);
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

		foreach ($old_luminaires as $ol) {
			foreach ($ol->getStatus() as $status) {
				$status->removeLuminaire($ol);
				$em->persist($status);
				$em->flush();
			}
			$em->remove($ol);
		}

		foreach ($clusters as $c) {
			$em->remove($c);
		}
		$em->flush();

		// Interroger le réseau de luminaires
    	$process = new Process('./bin/get_data.sh ');
		$process->run();

		// executes after the command finishes
		if (!$process->isSuccessful()) {
		    throw new ProcessFailedException($process);
		}

		$output = $process->getOutput();

		// Decode to array
		$data = json_decode($output, true);

		$i = 0;

		foreach ($data as $d) {

			$channels = $d["channels"];
			$pcbs = $d["pcb"];
			$status = $d["status"];

			// Create Luminaire by recusively adding channels / pcbs
			$luminaire = new Luminaire;
			$luminaire->setSerial($d["serial"]);
			$luminaire->setAddress($d["address"]);

			if ($status[0]["code"] == 0) {
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

			foreach ($status as $st) {
				$s = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findOneByCode($st["code"]);
				$luminaire->addStatus($s);
			}

			foreach ($channels as $channel) {
				$c = new Channel;
				$c->setChannel($channel["channel"]);
				$c->setIPeek($channel["i_peek"]);
				$c->setWaveLength($channel["wave_length"]);
				$c->setLedType($channel["led_type"]);
				$c->setPcb($channel["pcb"]);
				$c->setManuf($channel["manuf"]);

				$em->persist($c);

				$luminaire->addChannel($c);
			}
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
}
