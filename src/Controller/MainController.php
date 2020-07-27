<?php

namespace App\Controller;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

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


class MainController extends Controller
{
    /**
     * @Route("/", name="home")
     */
    public function index(Request $request)
    {

        $today = new \DateTime();
        $cluster_repo = $this->getDoctrine()->getRepository(Cluster::class);
        $run_repo = $this->getDoctrine()->getRepository(Run::class);
        $log_repo = $this->getDoctrine()->getRepository(Log::class);
        $luminaire_repo =$this->getDoctrine()->getRepository(Luminaire::class);
        $clusters = $cluster_repo->findBy(
            array(),
            array('label' => 'ASC')
        );
        // $luminaires = $luminaire_repo->findConnectedLuminaire();
        $luminaires = $luminaire_repo->findAll();
        $x_max = $luminaire_repo->getXMax();
        $y_max = $luminaire_repo->getYMax();

        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'clusters' => $clusters,
            'cluster_repo' => $cluster_repo,
            'run_repo' => $run_repo,
            'log_repo' => $log_repo,
            'navtitle' => 'dashboard.title',
            'luminaires' => $luminaires,
            'luminaire_repo' => $luminaire_repo,
            'x_max' => $x_max['x_max'],
            'y_max' => $y_max['y_max']
        ]);
    }

    /**
     * @Route("/parameters", name="parameters")
     */
    public function parameters(Request $request)
    {
        $to_update = "false";
        $process = new Process('git fetch origin master 2>/dev/null && git diff --shortstat origin/master -- changelog | wc -l');
        $process->setTimeout(3600);
        $process->run();
        if ($process->getOutput() > 0) {
            $to_update = "true";
        }

        $file_path = $this->getParameter('app.shared_dir').'/params.yaml';
        $netplan_file = $this->getParameter('app.shared_dir').'/netplan.yaml';

        $filesystem = new Filesystem();
        if ($filesystem->exists($file_path)) {
            $values = Yaml::parseFile($file_path);
        } else {
            $values = array(
                'controller_name' => 'test controller name',
                'ip_address' => 'xxx.xxx.xxx.xxx'
            );

            $yaml = Yaml::dump($values);
            file_put_contents($file_path, $yaml);
        }

        $form = $this->createFormBuilder()
            ->add('controller_name', null, array(
                'data' => $values['controller_name']
            ))
            ->add('ip_address', null, array(
                'data' => $values['ip_address']
            ))
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $controller_name = $form->get('controller_name')->getData();
            $ip_address = $form->get('ip_address')->getData();

            $values = array(
                'controller_name' => $controller_name,
                'ip_address' => $ip_address
            );

            $yaml = Yaml::dump($values);
            file_put_contents($file_path, $yaml);

            $contents = $this->renderView('var/netplan.yaml.twig', [
                'ip_address' => $values['ip_address']
            ]);
            $filesystem->dumpFile($netplan_file, $contents);

            return $this->redirectToRoute('home');

        }


        return $this->render('main/parameters.html.twig', [
            'form' => $form->createView(),
            'navtitle' => 'parameters.title',
            'to_update' => $to_update,
        ]);
        
    }

    /**
     * @Route("/update", name="update")
     */
    public function update(Request $request)
    {
        $process = new Process('git pull --rebase --autostash --stat origin master && rm -rf ../var/cache/*');
        $process->setTimeout(3600);
        $process->run();
        if (!$process->isSuccessful()) {
            
        }

        return $this->redirectToRoute('home');

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

        // Formulaire pour charger une config
        $form_upload = $this->createFormBuilder()
            ->add('file', FileType::class)
            ->add('save', SubmitType::class, array('label' => 'Load'))
            ->getForm();
        $form_upload->handleRequest($request);
        if ($form_upload->isSubmitted() && $form_upload->isValid()) {

            $file = $form_upload->get('file')->getData();

            if(!is_null($file)){
                // Generate a unique name for the file before saving it
                $fileName = "lightings.json";

                // Move the file to the directory where brochures are stored
                $file->move(
                    $this->getParameter('tmp_directory'),
                    $fileName
                );

                if($_SERVER['APP_ENV'] == 'dev') {
                    $json_file = $this->get('kernel')->getProjectDir()."/public/tmp/lightings_dev.json";
                } else {
                    // $json_file = $this->get('kernel')->getProjectDir()."/public/tmp/lightings.json";
                    $json_file = $this->getParameter('app.shared_dir')."/lightings.json";
                }

                $data = json_decode(file_get_contents($json_file), TRUE);

                $em = $this->getDoctrine()->getManager();

                foreach ($data["spots"] as $l) {

                    $_l = count($this->getDoctrine()->getRepository(Luminaire::class)->findBySerial($l['serial']));

                    if($_l == 0) { // On ajoute le luminaire s'il n'existe pas
                        $luminaire = new Luminaire;
                        $luminaire->setAddress($l['address']);
                        $luminaire->setSerial($l['serial']);
                        // $em->persist($luminaire);

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
                                'wavelength' => $channel['wl'],
                                'type' => $channel['type'],
                                'manufacturer' => $channel['manuf']));

                            if ($led == null) {
                                $le = new Led;
                                $le->setWavelength($channel['wl']);
                                $le->setType($channel['type']);
                                $le->setManufacturer($channel['manuf']);
                                $em->persist($le);
                                $em->flush();
                                $c->setLed($le);
                            } else {
                                $c->setLed($led);
                            }
                            $em->persist($c);
                        }
                    }
                }

                $em->flush();
                
            }

            return $this->redirectToRoute('my-lightings');
        }
    	
        return $this->render('setup/my-lightings.html.twig', [
        	'all_luminaires' => $all_luminaires,
        	'clusters' => $clusters,
        	'next_cluster' => $cluster_number+1,
            'form' => $form->createView(),
            'navtitle' => 'navtitle.mylightings',
            'form_upload' =>$form_upload->createView(),
        ]);
    }

    /**
     * @Route("/setup/my-lightings/download", name="download-my-lightings")
     */
    public function downloadMyLightings()
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
                    'id' => $channel->getChannel(),
                    'max' => $channel->getIPeek(),
                    // 'pcb' => $channel->getPcb(),
                    'wl' => $led->getWavelength(),
                    'type' => $led->getType(),
                    'manuf' => $led->getManufacturer()
                );
            }
            $output[] = array(
                'serial' => $luminaire->getSerial(), 
                'address' => $luminaire->getAddress(), 
                'pcb' => $p,
                'channels' => $c
            );
        }

        $response = new Response(json_encode(array("spots" => $output), JSON_FORCE_OBJECT));

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment;filename="data.json"');

        return $response;
    }

    /**
     * @Route("/setup/connected-lightings", name="connected-lightings")
     */
    public function connectedLighting()
    {
    	$installed_luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findConnectedLuminaire();

        // die(print_r($installed_luminaires));

		$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

        $empty_clusters = $this->getDoctrine()->getRepository(Cluster::class)->getEmptyClusters();

        $em = $this->getDoctrine()->getManager();
        // foreach ($empty_clusters as $cluster) {
        //     $em->remove($cluster);
        // }
        // $em->flush();

		// Compter les clusters existants
		$cluster_number = count($this->getDoctrine()->getRepository(Cluster::class)->findAll());
    	
        return $this->render('setup/connected-lightings.html.twig', [
        	'installed_luminaires' => $installed_luminaires,
        	'clusters' => $clusters,
        	'next_cluster' => $cluster_number+1,
            'navtitle' => 'navtitle.groups',
        ]);
    }

    /**
     * @Route("/setup/get-connected-lightings", name="get-connected-lightings")
     */
    public function getMyLightings()
    {
        $em = $this->getDoctrine()->getManager();
        $session = new Session();

        // Supprimer les luminaires existants
        $luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();
        // $clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

        $list = " --address ";

        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
            $_status = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findByLuminaire($luminaire);
            if (! is_null($_status)) {
                foreach ($_status as $s) {
                    $luminaire->removeStatus($s);
                }
            }
            $status = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findOneByCode(99);
            $luminaire->addStatus($status);
            // $luminaire->setCluster(null);
            // $luminaire->setLigne(null);
            // $luminaire->setColonne(null);
            // $em->persist($luminaire);
        }

        // foreach ($clusters as $cluster) {
        //     $em->remove($cluster);
        // }

        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel(1);
        if(is_null($cluster)){
            $cluster = new Cluster;
            $cluster->setLabel(1);
            $em->persist($cluster);
        }
        

        if($_SERVER['APP_ENV'] == 'dev') {
            $data = json_decode(file_get_contents($this->get('kernel')->getProjectDir()."/var/test.json"), TRUE);
        } else {
            // master/slave + set frequence à 2.5kHz + exctinction des drivers + tous les canaux à 0 + sauver dans la mémoire
            // à faire peu souvent car écriture mémoire limitée
            // !!!TODO!!! --address + liste des luminaires
            $process = new Process($this->getParameter('app.velire_cmd').$list.' --set-master --set-freq 2500 --set-power 0 --shutdown --write --quiet'); // --set-freq 2500 ??--set-power 0?? --shutdown --write
            $process->setTimeout(3600);
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Interroger le réseau de luminaires
            // !!!TODO!!! --address + liste des luminaires
            $process = new Process($this->getParameter('app.velire_cmd').$list.' --search --json --quiet');
            $process->setTimeout(3600);
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();

            // Decode to array
            $data = json_decode($output, true);
        }

        $spots = $data['found'];

        // $i = 0;
        // $y = 1;
        // $x = 1;

        foreach ($spots as $spot) {

            $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($spot);
            
            if(! is_null($luminaire)){
                $status_on = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findOneByCode(0);
                $status_off = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findOneByCode(99);
                $luminaire->removeStatus($status_off);
                $luminaire->addStatus($status_on);
                if(is_null($luminaire->getCluster())){
                    $luminaire->setCluster($cluster);
                }
                // $luminaire->setCluster($cluster);
                // $luminaire->setColonne($x);
                // $luminaire->setLigne($y);
                // $em->persist($luminaire);
                // $i++;
                // if($x < 5){
                //     $x++;
                // } else {
                //     $x = 1;
                //     $y++;
                // }
            }
        }

        $em->flush();

        if($_SERVER['APP_ENV'] == 'prod') {
            // Initialiser master/slave
            // $spots = implode(" ", $data['found']);

            // Interroger le réseau de luminaires
            // !!!TODO!!! --address + liste des luminaires
            $process = new Process($this->getParameter('app.velire_cmd').$list.' --get-info specs --quiet --json --output-file '.$this->getParameter('app.shared_dir').'/config.json');
            $process->setTimeout(3600);
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        } 

        $data = json_decode(file_get_contents($this->getParameter('app.shared_dir').'/config.json'), TRUE);

        $luminaires = $data['specs']; //specs

        $i = 0;

        foreach ($luminaires as $l) { 
            $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($l['address']);

            if(!is_null($luminaire)) {
                $i++;
                if(is_null($luminaire->getSerial())) {
                    $luminaire->setSerial($l['SN']); // SN
                    // récupérer firmware-version ? >> à ajouter dans la base de données
                    // $em->persist($luminaire);
                    foreach ($l['pcb-led'] as $key => $pcb) { // pcb-led
                        $p = $this->getDoctrine()->getRepository(Pcb::class)->findOneBySerial($pcb["SN"]);
                        if(is_null($p)){
                            $p = new Pcb;
                            $p->setCrc($pcb['desc']["crc"]); // desc > crc
                            $p->setSerial($pcb["SN"]); // SN
                            $p->setN($key); // c'est la clé.. comment on la récupère ?
                            $p->setType($pcb["type"]);
                            $em->persist($p);
                            $luminaire->addPcb($p);
                        }                        
                    }
                    $em->persist($luminaire);
                    foreach ($l['pcb-led'][0]['desc']['channels'] as $key => $channel) { // dans desc > channels
                        $c = new Channel;
                        $c->setChannel($key); // c'est la clé.. comment on la récupère ?
                        $c->setIPeek($channel["i_peek"]); // i_peek
                        // $c->setPcb($channel["pcb"]);
                        $c->setLuminaire($luminaire);
                        // $em->persist($c);

                        # Vérifie que la Led existe dans la base de données, sinon l'ajoute.
                        $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(array(
                            'wavelength' => $channel["wl"],
                            'type' => $channel["col"], // col
                            'manufacturer' => $channel["manuf"]));

                        if ($led == null) {
                            $le = new Led;
                            $le->setWavelength($channel["wl"]);
                            $le->setType($channel["col"]); // col
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
            }
            
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
     * @Route("/setup/recipes", name="recipes")
     */
    public function recipes(Request $request)
    {
        $led_repo = $this->getDoctrine()->getRepository(Led::class);
        $recipe_repo = $this->getDoctrine()->getRepository(Recipe::class);
        $clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();
        $recipes = $recipe_repo->findAll();

        // Formulaire pour charger une config
        $form_upload = $this->createFormBuilder()
            ->add('file', FileType::class)
            ->add('save', SubmitType::class, array('label' => 'Load'))
            ->getForm();
        $form_upload->handleRequest($request);
        if ($form_upload->isSubmitted() && $form_upload->isValid()) {

            $file = $form_upload->get('file')->getData();

            if(!is_null($file)){
                // Generate a unique name for the file before saving it
                $fileName = "recipes.json";

                // Move the file to the directory where brochures are stored
                $file->move(
                    $this->getParameter('tmp_directory'),
                    $fileName
                );

                $data = json_decode(file_get_contents($this->getParameter('tmp_directory')."/recipes.json"), TRUE);

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
                
                return $this->redirectToRoute('recipes');
            }
        }
        
        return $this->render('setup/recipes.html.twig', [
            'clusters' => $clusters,
            'led_repo' => $led_repo,
            'recipe_repo' => $recipe_repo,
            'recipes' => $recipes,
            'navtitle' => 'navtitle.recipes',
            'form_upload' => $form_upload->createView(),
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
        $uuid = uuid_create(UUID_TYPE_RANDOM);
        $recipe->setUuid($uuid);
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
            'navtitle' => 'recipes.new.title',
        ]);
    }

    /**
     * @Route("/setup/recipes/edit/{id}", name="edit-recipe")
     */
    public function editRecipe(Request $request, Recipe $recipe)
    {
        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            // $em->persist($recipe);
            $em->flush();

            return $this->redirectToRoute('recipes');
        }
        
        return $this->render('setup/new-recipes.html.twig', [
            'form' => $form->createView(),
            'edit' => true,
            'navtitle' => 'Edit Recipe',
        ]);
    }

    /**
     * @Route("/setup/recipes/test", name="test-recipe", options={"expose"=true})
     */
    public function testRecipe(Request $request)
    {
        $data = $request->get('data');
        $labels = $data['labels'];
        $intensities = $data['intensities'];
        $commands = $data['commands'];

        $luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();
        $list = " --address ";
        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
        }

        // !!!TODO!!! --address + liste des luminaires
        // --exclusive met les canaux non appelés à 0
        // --set-colors $label1 $intensity1 $label$2 $intensity$2 --set-power 1
        // peut-être modifier test.js pour produire directement les combinaisons 'labels intensities'
        $command = $this->getParameter('app.velire_cmd').$list.' --exclusive --set-power 1 --set-colors '.implode(" ", $commands);

        # Envoyer la commande aux luminaires
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();

        
        $response = new JsonResponse(array(
            'l' => $labels,
            'i' => $intensities,
            'command' => $command,
        ));
        return $response;
    }

    /**
     * @Route("/setup/recipes/delete/{id}", name="delete-recipe")
     */
    public function deleteRecipe(Request $request, Recipe $recipe)
    {
        $em = $this->getDoctrine()->getManager();

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
     * @Route("/setup/luminaire/unmap/{id}", name="unmap-luminaire")
     */
    public function unmapLuminaire(Request $request, Luminaire $luminaire)
    {
        $em = $this->getDoctrine()->getManager();

        $luminaire->setLigne(NULL);
        $luminaire->setColonne(NULL);

        $em->persist($luminaire);
        $em->flush();
        
        return $this->redirectToRoute('home');        
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
            $new_cluster->setLabel($c);
            $new_cluster->addLuminaire($luminaire);
            $em->persist($new_cluster);
            $cluster_added = 1;
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

    	$luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findConnectedLuminaire();
    	$clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

    	// foreach ($clusters as $cluster) {
    	// 	$em->remove($cluster);
    	// }

        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel(1);

        if(is_null($cluster)){
		  $cluster = new Cluster;
		  $cluster->setLabel(1);
		  $em->persist($cluster);
        }
    	
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

        $luminaires = $cluster->getLuminaires();
        $list = " --address ";
        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
        }

        // Interroger le réseau de luminaires
        $process = new Process($this->getParameter('app.velire_cmd').$list.' --shutdown');
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
        // $em = $this->getDoctrine()->getManager();
        
        // // Interroger le réseau de luminaires
        // // $process = new Process('./bin/velire.sh --log');
        // $luminaires = $this->getDoctrine()->getRepository(Luminaire::class)->findAll();
        // $opt = "-s ";
        // foreach ($luminaires as $l) {
        //     $opt = $opt.$l->getAddress().' ';
        // }

        // !!!TODO!!! deprecated !!!!
        //$cmd = $this->getParameter('app.velire_cmd');
        //$cmd .= ' '.$opt.' --logdb;';
        // $cmd .= $this->getParameter('app.velire_cmd');
        // $cmd .= ' --snapshot '.$this->getParameter('app.shared_dir').'/snapshot.png';

        // $process = new Process($cmd);
        // $process->setTimeout(3600);
        // $process->run();

        // // executes after the command finishes
        // if (!$process->isSuccessful()) {
        //     throw new ProcessFailedException($process);
        // }

        // $output = json_decode($process->getOutput(), true);

        // $cluster_info = array();

        // foreach ($output['spots'] as $spot) {
        //     $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($spot["address"]);
        //     $channels = $spot['channels'];
        //     $channels_on = array();
        //     foreach ($channels as $channel) {
        //         if($channel['intensity'] > 0) {
        //             $channels_on[] = array('color' => $channel['color'], 'intensity' => $channel['intensity']);
        //         }
        //     }
        //     $luminaire_info = array(
        //         'address' => $spot['address'], 
        //         'serial' => $spot['serial'], 
        //         'led_pcb_0' => $spot['temperature']['led_pcb_0'], 
        //         'led_pcb_1' => $spot['temperature']['led_pcb_1'],
        //         'channels_on' => $channels_on
        //     );
        //     $cluster_info[$luminaire->getCluster()->getId()]['temp'] = array($spot['temperature']['led_pcb_0'],$spot['temperature']['led_pcb_1']);

        //     // die(print_r(count($channels_on)));

        //     $log = new Log();
        //     $log->setTime(new \DateTime);
        //     $log->setType("luminaire_info");
        //     $log->setLuminaire($luminaire);
        //     $log->setCluster($luminaire->getCluster());
        //     $log->setValue($luminaire_info);

        //     $em->persist($log);
        // }

        // $em->flush();

        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/setup/set-cluster", name="set-cluster", options={"expose"=true})
     */
    public function setCluster(Request $request)
    {
        $data = $request->get('data');
        $l = $data['l'];
        $c = $data['c'];

        $em = $this->getDoctrine()->getManager();
        // $session = new Session();

        $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($l);
        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($c);
        // Compter les clusters existants
        $cluster_number = count($this->getDoctrine()->getRepository(Cluster::class)->findAll());

        $cluster_added = 1; // TODO faire le changement de couleur en JQUERY pour éviter le recharcgement de la page

        if (count($cluster) == 0) {
            $new_cluster = new Cluster;
            $new_cluster->setLabel($c);
            $new_cluster->addLuminaire($luminaire);
            $em->persist($new_cluster);
            $cluster_added = 1;
            // $luminaire->setCluster($new_cluster);
            // $em->persist($luminaire);
        } else {
            $luminaire->setCluster($cluster);
            $em->persist($luminaire);
        }
        $em->flush();

        // $clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();
        // foreach($clusters as $item) {
        //     if(count($item->getLuminaires()) == 0){
        //         $em->remove($item);
        //         // die(print_r($item->getId()));
        //         $removed = count($item->getLuminaires());
        //         $cluster_added = 1;
        //     }
        //     $em->flush();
        // }

        $response = new JsonResponse(array(
            'c' => $c,
            'l' => $l,
            'cluster_added' => $cluster_added,
        ));
        return $response;
    }

    /**
     * @Route("/update/luminaire", name="update-luminaire")
     */
    public function updateLuminaire(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        $em = $this->getDoctrine()->getManager();

        foreach ($data as $d) {
            $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($d['address']);

            $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($d['cluster']['label']);
            if(is_null($cluster)){
                $cluster = new Cluster;
                $cluster->setLabel($d['cluster']['label']);
                $em->persist($cluster);
                $em->flush();
            }

            if(is_null($luminaire)) {
                $luminaire = new Luminaire;
                $luminaire->setAddress($d['address']);
                $luminaire->setSerial($d['serial']);
                $luminaire->setLigne($d['ligne']);
                $luminaire->setColonne($d['colonne']);
                $luminaire->setCluster($cluster);
                $em->persist($luminaire);
            } else {
                $luminaire->setAddress($d['address']);
                $luminaire->setSerial($d['serial']);
                $luminaire->setLigne($d['ligne']);
                $luminaire->setColonne($d['colonne']);
                $luminaire->setCluster($cluster);
            }
        }

        $em->flush();

        // réinitialiser + master/slave
        // !!! TODO !! voir plus haut les modifs à ces commandes
        $process = new Process($this->getParameter('app.velire_cmd').' --init --quiet');
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Interroger le réseau de luminaires
        // !!! TODO !! voir plus haut les modifs à ces commandes
        $process = new Process($this->getParameter('app.velire_cmd').' --info all --quiet --json --output '.$this->getParameter('app.shared_dir').'/config.json');
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $data = json_decode(file_get_contents($this->getParameter('app.shared_dir')."/config.json"), TRUE);

        $luminaires = $data['spots'];

        foreach ($luminaires as $l) { 
            $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($l['address']);

            if(count($luminaire->getPcbs()) == 0) {
                $luminaire->setSerial($l['serial']);
                // $em->persist($luminaire);

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
        }

        $em->flush();

        return new Response(
            ' lightings were detected and successfully installed.',
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }
}
