<?php

namespace App\Controller;


use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;


use App\Entity\Program;
use App\Entity\Step;
use App\Entity\Run;
use App\Entity\RunStep;
use App\Entity\Cluster;
use App\Entity\Recipe;
use App\Entity\Luminaire;
use App\Entity\Ingredient;
use App\Entity\Led;
use App\Entity\Pcb;
use App\Entity\Channel;
use App\Entity\LuminaireStatus;
use App\Entity\Log;


use App\Form\ProgramType;
use App\Form\StepType;
use App\Form\RunType;
use App\Form\RunEditType;

use App\Services\Lumiatec;

class ProgramController extends AbstractController
{
    /**
     * @Route("/program", name="program")
     */
    public function indexProgram()
    {
        $programs = $this->getDoctrine()->getRepository(Program::class)->findAll();

        return $this->render('program/index.html.twig', [
            'controller_name' => 'ProgramController',
            'programs' => $programs,
            'navtitle' => 'programs.title', 
        ]);
    }

    /**
     * @Route("/program/new", name="new-program")
     */
    public function newProgram(Request $request)
    {
    	$program = new Program;
        $program->setTimestamp(time());
        $uuid = uuid_create(UUID_TYPE_RANDOM);
        $program->setUuid($uuid);
    	$form = $this->createForm(ProgramType::class, $program);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $data = $form->getData();
            foreach ($data->getSteps() as $step) {
            	$step->setProgram($program);
            	$em->persist($step);
            }
            $em->persist($program);
            $em->flush();

            return $this->redirectToRoute('program');
        }
        return $this->render('program/new-program.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'programs.new.title',
        ]);
    }

     /**
     * @Route("/program/edit/{id}", name="edit-program")
     */
    public function editProgram(Request $request, Program $program)
    {
    	$em = $this->getDoctrine()->getManager();

	   	$originalSteps = new ArrayCollection();

	    foreach ($program->getSteps() as $step) {
	        $originalSteps->add($step);
	    }

        $program->setTimestamp(time());
    	$form = $this->createForm(ProgramType::class, $program);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

        	foreach ($originalSteps as $step) {
            	if (false === $program->getSteps()->contains($step)) {
            		$program->removeStep($step);
            		$em->remove($step);
            	}
            }
            
            $data = $form->getData();
            foreach ($data->getSteps() as $step) {
            	$step->setProgram($program);
            	$em->persist($step);
            }
            $em->persist($program);

            $em->flush();

            // Le Run en cours qui utilisent ce programme doivent être relancés
            // pour intégrer les modifications
            $runs = $program->getRuns();

            foreach ($runs as $run) {
                $run_steps = $run->getSteps();
                foreach ($run_steps as $run_step) {
                    $em->remove($run_step);
                }
                $em->flush();

                // !!! TODO !!! à reprendre dans la lib bash ?
                $process = new Process($this->getParameter('app.velire_cmd').'-e --input '.$this->getParameter('app.shared_dir').'/config.json --set-run '.$run->getId());
                $process->run();
            }

            foreach ($runs as $run) {
                $run_steps = $run->getSteps();
            }

            return $this->redirectToRoute('program');
        }
        return $this->render('program/new-program.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'programs.edit.title',
            'edit' => true,
        ]);
    }

    /**
     * @Route("/program/delete/{id}", name="delete-program")
     */
    public function deleteProgram(Request $request, Program $program)
    {
        $em = $this->getDoctrine()->getManager();

        foreach ($program->getSteps() as $step) {
            $em->remove($step);
        }

        $em->remove($program);
        $em->flush();
        
        return $this->redirectToRoute('program');        
    }

    /**
     * @Route("/run", name="run")
     */
    public function indexRun()
    {
        $time = date('Y-m-d H:i:00');
        $running_runs = $this->getDoctrine()->getRepository(Run::class)->getRunningRuns($time);
        $coming_runs = $this->getDoctrine()->getRepository(Run::class)->getComingRuns($time);
        $past_runs = $this->getDoctrine()->getRepository(Run::class)->getPastRuns($time);
        $clusters = $this->getDoctrine()->getRepository(Cluster::class)->findAll();

        return $this->render('control/runs.html.twig', [
            'controller_name' => 'ProgramController',
            'running_runs' => $running_runs,
            'coming_runs' => $coming_runs,
            'past_runs' => $past_runs,
            'clusters' => $clusters,
            'navtitle' => 'runs.title', 
        ]);
    }

    /**
     * @Route("/run/new/{id}", name="new-run")
     */
    public function newRun(Request $request, Lumiatec $lumiatec, Cluster $cluster)
    {
        $em = $this->getDoctrine()->getManager();

        $run = new Run;
        $run->setTimestamp(time());
        $uuid = uuid_create(UUID_TYPE_RANDOM);
        $run->setUuid($uuid);
        $run->setCluster($cluster);
        $form = $this->createForm(RunType::class, $run);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $em->persist($run);
            $em->flush();

            $lumiatec->setRun($run);

            return $this->redirectToRoute('run');
        }
        return $this->render('control/new-run.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'runs.new.title',
        ]);
    }

    /**
     * @Route("/play/new/{id}", name="new-play")
     */
    public function newPlay(Request $request, Lumiatec $lumiatec, Cluster $cluster)
    {        
        # Form to play
        $form = $this->createFormBuilder()
            ->add('recipe', EntityType::class, [
                'class' => Recipe::class,
                'choice_label' => 'label',
                'choice_value' => 'id', // <--- default IdReader::getIdValue()
            ])
            // ->add('cluster', HiddenType::class, array(
            //     // 'mapped' => false,
            // ))
            ->getForm()
            ;

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $recipe = $data['recipe'];
            $frequency = $recipe->getFrequency();
            if(is_null($frequency)) {
                // default frequency
                $filesystem = new Filesystem();
                if ($filesystem->exists($this->getParameter('app.shared_dir').'/params.yaml')) {
                    $values = Yaml::parseFile($this->getParameter('app.shared_dir').'/params.yaml');
                    $frequency = $values['frequency'];
                } else {
                    $frequency = 2500;
                }
            }
            // $recipe = $this->getDoctrine()->getRepository(Recipe::class)->find($r);
            $commands = [];
            $ingredients = $recipe->getIngredients();
            foreach ($ingredients as $ingredient) {
                $level = $ingredient->getLevel();
                $led = $ingredient->getLed();
                $color = $led->getType()."_".$led->getWavelength();
                $commands[] = $color." ".$level." ".$ingredient->getPwmStart()." ".$ingredient->getPwmStop();
            }

            $luminaires = $cluster->getLuminaires();
            $list = " --address ";
            foreach ($luminaires as $luminaire) {
                $list .= $luminaire->getAddress()." ";
            }

            $args = $list.' --exclusive --set-power 1 --set-freq '.$frequency.' --set-colors '.implode(" ", $commands);
            $success_msg = 'Recipe '.$recipe->getLabel().' successfully started on cluster '.$cluster->getLabel();
            $error_msg = 'For a unknown reason, the recipe was not started';

            $lumiatec->sendCmd($args, $success_msg, $error_msg);
            $lumiatec->updateLogs();   

            return $this->redirectToRoute('home');     
        }

        return $this->render('control/new-play.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'dashboard.play-recipe.title',
        ]);
    }

    /**
     * @Route("/run/delete/{id}", name="delete-run")
     */
    public function deleteRun(Request $request, Run $run)
    {
        $em = $this->getDoctrine()->getManager();

        $steps = $run->getSteps();

        foreach ($steps as $step) {
            $em->remove($step);
        }

        // $process = new Process('./bin/velire.sh --delete-run'.$run->getCluster()->getId());
        // $process->run();

        $em->remove($run);
        $em->flush();
        
        return $this->redirectToRoute('run');        
    }

    /**
     * @Route("/run/edit/{id}", name="edit-run")
     */
    public function editRun(Request $request, Lumiatec $lumiatec, Run $run)
    {
        $em = $this->getDoctrine()->getManager();

        $run->setTimestamp(time());
        $form = $this->createForm(RunEditType::class, $run);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // $data = $form->getData();

            $steps = $run->getSteps();
            foreach ($steps as $step) {
                $em->remove($step);
            }

            $em->persist($run);
            $em->flush();

            $lumiatec->setRun($run);

            return $this->redirectToRoute('run');
        }
        return $this->render('control/new-run.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'runs.edit.title',
            'edit' => true
        ]);
    }

    /**
     * @Route("/program/manual", name="manual")
     */
    public function manualControl(Request $request)
    {
        $session = new Session;
        $cluster_repo = $this->getDoctrine()->getRepository(Cluster::class);
        $clusters = $cluster_repo->findAll();

        $form = $this->createFormBuilder()
            ->add('recipe', EntityType::class, [
                'class' => Recipe::class,
                'choice_label' => 'label',
                'choice_value' => 'id', // <--- default IdReader::getIdValue()
            ])
            ->add('cluster', HiddenType::class, array(
                // 'mapped' => false,
            ))
            ->getForm()
            ;

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $cluster_id = $data['cluster'];
            $recipe = $data['recipe'];

            $process = new Process('python3 ./bin/velire-cmd.py -e --config ./bin/config.yaml --input ../var/config.json --cluster '.$cluster_id.' --play '.$recipe->getId());
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            } else {
                            // add flash messages
                $session->getFlashBag()->add(
                    'info',
                    $process->getOutput()
                );
            }
            // die(var_dump($data['cluster']));
        }


        return $this->render('control/manual-control.html.twig', [
            'controller_name' => 'ProgramController',
            'clusters' => $clusters,
            'cluster_repo' => $cluster_repo,
            'form' => $form->createView(),
            'navtitle' => 'Manual Control',
        ]);
    }

    /**
     * @Route("/set-position", name="set-position", options={"expose"=true})
     */
    public function setPositionAction(Request $request)
    {
        $data = $request->get('data');
        $id = $data['id'];
        $x = $data['x'];
        $y = $data['y'];

        $em = $this->getDoctrine()->getManager();

        $test_luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->getByXY($x,$y);
        $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->find($id);

        if(is_null($test_luminaire)) {
            $luminaire->setColonne($x);
            $luminaire->setLigne($y);
            $em->persist($luminaire);
            $em->flush();
        } else {
            $test_luminaire->setColonne(null);
            $test_luminaire->setLigne(null);
            $luminaire->setColonne($x);
            $luminaire->setLigne($y);
            $em->persist($luminaire);
            $em->persist($test_luminaire);
            $em->flush();
        }

        $x_max = $this->getDoctrine()->getRepository(Luminaire::class)->getXMax();
        $y_max = $this->getDoctrine()->getRepository(Luminaire::class)->getYMax();

        $response = new JsonResponse(array(
            'id' => $id,
            'x_max' => $x_max['x_max'],
            'y_max' => $y_max['y_max']
        ));
        return $response;
    }

    /**
     * @Route("/remote/play", name="play-from-remote")
     */
    public function playFromRemote(Request $request, Lumiatec $lumiatec)
    {
        $data = json_decode($request->getContent(), true);

        $em = $this->getDoctrine()->getManager();

        $recipe = $this->getDoctrine()->getRepository(Recipe::class)->findOneByUuid($data['recipe']['uuid']);

        if(is_null($recipe)){
            $recipe = new Recipe;
            $recipe->setUuid($data['recipe']['uuid']);
            $recipe->setLabel($data['recipe']['label']);
            $recipe->setDescription($data['recipe']['description']);
            $recipe->setTimestamp($data['recipe']['timestamp']);
            if(is_null($data['recipe']['frequency'])){
                // default frequency
                $filesystem = new Filesystem();
                if ($filesystem->exists($this->getParameter('app.shared_dir').'/params.yaml')) {
                    $values = Yaml::parseFile($this->getParameter('app.shared_dir').'/params.yaml');
                    $frequency = $values['frequency'];
                } else {
                    $frequency = 2500;
                }
                $recipe->setFrequency($frequency);
            } else {
                $recipe->setFrequency($data['recipe']['frequency']);
            }

            foreach ($data['recipe']['ingredients'] as $i) {

                $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(
                    array(
                        "wavelength" => $i['led']['wavelength'],
                        "type" => $i['led']['type'],
                        "manufacturer" => $i['led']['manufacturer']
                    )
                );

                if(is_null($led)) {
                    $led = new Led;
                    $led->setWavelength($i['led']['wavelength']);
                    $led->setType($i['led']['type']);
                    $led->setManufacturer($i['led']['manufacturer']);
                    $em->persist($led);
                }

                $ingredient = new Ingredient;
                $ingredient->setLed($led);
                $ingredient->setLevel($i['level']);
                $em->persist($ingredient);
                $recipe->addIngredient($ingredient);
            }
            $em->persist($recipe);
            $em->flush();

            $msg = "null";
        } else {
            if($recipe->getTimestamp() < $data['recipe']['timestamp']) {
                $recipe->setLabel($data['recipe']['label']);
                $recipe->setDescription($data['recipe']['description']);
                $recipe->setTimestamp($data['recipe']['timestamp']);
                foreach ($recipe->getIngredients() as $ingredient) {
                    $em->remove($ingredient);
                }
                // default frequency
                if(is_null($data['recipe']['frequency'])){
                    // default frequency
                    $filesystem = new Filesystem();
                    if ($filesystem->exists($this->getParameter('app.shared_dir').'/params.yaml')) {
                        $values = Yaml::parseFile($this->getParameter('app.shared_dir').'/params.yaml');
                        $frequency = $values['frequency'];
                    } else {
                        $frequency = 2500;
                    }
                    $recipe->setFrequency($frequency);
                } else {
                    $recipe->setFrequency($data['recipe']['frequency']);
                }

                foreach ($data['recipe']['ingredients'] as $i) {
                    $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(
                        array(
                            "wavelength" => $i['led']['wavelength'],
                            "type" => $i['led']['type'],
                            "manufacturer" => $i['led']['manufacturer']
                        )
                    );

                    if(is_null($led)) {
                        $led = new Led;
                        $led->setWavelength($i['led']['wavelength']);
                        $led->setType($i['led']['type']);
                        $led->setManufacturer($i['led']['manufacturer']);
                        $em->persist($led);
                    }
                    $ingredient = new Ingredient;
                    $ingredient->setLed($led);
                    $ingredient->setLevel($i['level']);
                    $ingredient->setPwmStart($i['pwm_start']);
                    $ingredient->setPwmStop($i['pwm_stop']);
                    $em->persist($ingredient);
                    $recipe->addIngredient($ingredient);
                }
                $em->flush();
            }
            if($recipe->getTimestamp() > $data['recipe']['timestamp']) {
                
                    //TODO message erreur  
            }
        }

        $commands = [];
        $ingredients = $recipe->getIngredients();
        foreach ($ingredients as $ingredient) {
            $level = $ingredient->getLevel();
            $led = $ingredient->getLed();
            $color = $led->getType()."_".$led->getWavelength();
            $commands[] = $color." ".$level." ".$ingredient->getPwmStart()." ".$ingredient->getPwmStop();
        }

        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($data['cluster']);

        $luminaires = $cluster->getLuminaires();
        $list = " --address ";
        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
        }

        $args = $list.' --exclusive --set-power 1 --set-freq '.$recipe->getFrequency().' --set-colors '.implode(" ", $commands);
        $success_msg = 'Recipe '.$recipe->getLabel().' successfully started on cluster '.$cluster->getLabel();
        $error_msg = 'For a unknown reason, the recipe was not started';

        $result = $lumiatec->sendCmd($args, $success_msg, $error_msg);
        $lumiatec->updateLogs();

        return new Response(
            $result['message'],
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/remote/update/recipe", name="update-recipe-from-remote")
     */
    public function updateRecipeFromRemote(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        $em = $this->getDoctrine()->getManager();

        $r = $this->getDoctrine()->getRepository(Recipe::class)->findOneByUuid($data['recipe']['uuid']);

        if(is_null($r)){
            $recipe = new Recipe;
        } else {
            $recipe = $r;
        }

        $recipe->setUuid($data['recipe']['uuid']);
        $recipe->setLabel($data['recipe']['label']);
        $recipe->setDescription($data['recipe']['description']);
        $recipe->setTimestamp($data['recipe']['timestamp']);
        if(is_null($data['recipe']['frequency'])){
            // default frequency
            $filesystem = new Filesystem();
            if ($filesystem->exists($this->getParameter('app.shared_dir').'/params.yaml')) {
                $values = Yaml::parseFile($this->getParameter('app.shared_dir').'/params.yaml');
                $frequency = $values['frequency'];
            } else {
                $frequency = 2500;
            }
            $recipe->setFrequency($frequency);
        } else {
            $recipe->setFrequency($data['recipe']['frequency']);
        }

        if(!is_null($r)) {
            foreach ($recipe->getIngredients() as $ingredient) {
                $em->remove($ingredient);
            }
        }

        foreach ($data['recipe']['ingredients'] as $i) {

            $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(
                array(
                    "wavelength" => $i['led']['wavelength'],
                    "type" => $i['led']['type'],
                    "manufacturer" => $i['led']['manufacturer']
                )
            );

            if(is_null($led)) {
                $led = new Led;
                $led->setWavelength($i['led']['wavelength']);
                $led->setType($i['led']['type']);
                $led->setManufacturer($i['led']['manufacturer']);
                $em->persist($led);
            }

            $ingredient = new Ingredient;
            $ingredient->setLed($led);
            $ingredient->setLevel($i['level']);
            $ingredient->setPwmStart($i['pwm_start']);
            $ingredient->setPwmStop($i['pwm_stop']);
            $em->persist($ingredient);
            $recipe->addIngredient($ingredient);
        }

        if(is_null($r)) {
            $em->persist($recipe);
        }
        
        $em->flush();

        return new Response(
            'Recipe '.$recipe->getLabel().' successfully updated ',
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/remote/update/program", name="update-program-from-remote")
     */
    public function updateProgramFromRemote(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        $em = $this->getDoctrine()->getManager();

        $program = $this->getDoctrine()->getRepository(Program::class)->findOneByUuid($data['program']['uuid']);

        $message = 'updated';
        $flag = false;
        if(is_null($program)){
            $program = new Program;
            $flag = true;
            $message = 'created';
        } elseif ($program->getTimestamp() == $data['program']['timestamp']) {
            return new Response(
                'exists',
                Response::HTTP_OK,
                ['content-type' => 'text/html']
            );
        }

        $program->setUuid($data['program']['uuid']);
        $program->setLabel($data['program']['label']);
        $program->setDescription($data['program']['description']);
        $program->setTimestamp($data['program']['timestamp']);

        if(!$flag) {
            foreach ($program->getSteps() as $step) {
                $em->remove($step);
            }
        }

        foreach ($data['program']['steps'] as $s) {
            $step = new Step;
            $step->setType($s['type']);
            $step->setRank($s['rank']);
            $step->setValue($s['value']);
            if(!is_null($s['recipe'])){
                $recipe = $this->getDoctrine()->getRepository(Recipe::class)->findOneBy(array('uuid' => $s['recipe']['uuid']));
                $step->setRecipe($recipe);
            }
            $em->persist($step);
            $program->addStep($step);
        }

        if($flag) {
            $em->persist($program);
        }
        
        $em->flush();

        return new Response(
            $message,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/remote/shutdown", name="shutdown-from-remote")
     */
    public function shutdownFromRemote(Request $request, Lumiatec $lumiatec)
    {
        $data = json_decode($request->getContent(), true);

        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($data['cluster']);

        $luminaires = $cluster->getLuminaires();
        $list = " --address ";
        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
        }

        $args = $list.' --shutdown';
        $success_msg = 'Network scanning successful !';
        $error_msg = 'For a unknown reason, network scanning failed !';

        $result = $lumiatec->sendCmd($args, $success_msg, $error_msg);
        $lumiatec->updateLogs();

        return new Response(
            'Cluster '.$cluster->getLabel().' has been switched off.',
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/remote/run", name="run-from-remote")
     */
    public function runFromRemote(Request $request, Lumiatec $lumiatec)
    {
        $data = json_decode($request->getContent(), true);

        // dd($data);

        $em = $this->getDoctrine()->getManager();

        $program = $this->getDoctrine()->getRepository(Program::class)->findOneByUuid($data['program']['uuid']);

        if(is_null($program)){
            $program = new Program;
            $program->setUuid($data['program']['uuid']);
            $program->setLabel($data['program']['label']);
            $program->setDescription($data['program']['description']);
            foreach ($data['program']['steps'] as $s) {
                $step = new Step;
                $step->setType($s['type']);
                $step->setRank($s['rank']);
                $step->setValue($s['value']);
                $step->setProgram($program);
                if (!is_null($s['recipe'])) {
                    $recipe = $this->getDoctrine()->getRepository(Recipe::class)->findOneByLabel($s['recipe']['uuid']);
                    if(is_null($recipe)){
                        $recipe = new Recipe;
                        $recipe->setUuid($s['recipe']['uuid']);
                        $recipe->setLabel($s['recipe']['label']);
                        $recipe->setDescription($s['recipe']['description']);
                        if(is_null($s['recipe']['frequency'])) {
                            // default frequency
                            $filesystem = new Filesystem();
                            if ($filesystem->exists($this->getParameter('app.shared_dir').'/params.yaml')) {
                                $values = Yaml::parseFile($this->getParameter('app.shared_dir').'/params.yaml');
                                $frequency = $values['frequency'];
                            } else {
                                $frequency = 2500;
                            }
                            $recipe->setFrequency($frequency);
                        }
                        foreach ($s['recipe']['ingredients'] as $i) {

                            $led = $this->getDoctrine()->getRepository(Led::class)->findOneBy(
                                array(
                                    "wavelength" => $i['led']['wavelength'],
                                    "type" => $i['led']['type'],
                                    "manufacturer" => $i['led']['manufacturer']
                                )
                            );

                            if(is_null($led)) {
                                $led = new Led;
                                $led->setWavelength($i['led']['wavelength']);
                                $led->setType($i['led']['type']);
                                $led->setManufacturer($i['led']['manufacturer']);
                                $em->persist($led);
                            }

                            $ingredient = new Ingredient;
                            $ingredient->setLed($led);
                            $ingredient->setLevel($i['level']);
                            $ingredient->setPwmStart($i['pwm_start']);
                            $ingredient->setPwmStop($i['pwm_stop']);
                            $em->persist($ingredient);
                            $recipe->addIngredient($ingredient);
                        }
                        $em->persist($recipe);
                    }
                    $step->setRecipe($recipe);
                }
                $em->persist($step);
            }
            $em->persist($program);
            $em->flush();
        }

        $run = new Run;
        $now = new \DateTime($data['run']['start']['date']);
        $run->setStart($now);
        $run->setLabel($data['run']['label']);
        $run->setDescription($data['run']['description']);
        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($data['cluster']);
        $run->setCluster($cluster);
        $run->setProgram($program);
        $run->setUuid($data['run']['uuid']);

        $em->persist($run);
        $em->flush();

        $lumiatec->setRun($run); 

        return new Response(
            'Run '.$run->getLabel().' successfully started on cluster '.$cluster->getLabel(),
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/remote/run/delete", name="delete-run-from-remote")
     */
    public function deleteRunFromRemote(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $data = json_decode($request->getContent(), true);

        $run = $this->getDoctrine()->getRepository(Run::class)->findOneByUuid($data['uuid']);

        if(is_null($run))
        {
            return new Response(
                'Run not found on controller ',
                Response::HTTP_NO_CONTENT,
                ['content-type' => 'text/html']
            );
        }

        $steps = $run->getSteps();

        foreach ($steps as $step) {
            $em->remove($step);
        }

        // $process = new Process('./bin/velire.sh --delete-run'.$run->getCluster()->getId());
        // $process->run();

        $em->remove($run);
        $em->flush();
        
        return new Response(
            'Run '.$run->getLabel().' successfully removed ',
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );   
    }

    /**
     * @Route("/remote/luminaire/link", name="link-luminaire-from-remote")
     */
    public function linkLuminaireFromRemote(Request $request, Lumiatec $lumiatec)
    {
        $em = $this->getDoctrine()->getManager();

        $data = json_decode($request->getContent(), true);

        //data: {"address":30,"serial":"0x12B0001E","ligne":2,"colonne":3,"cluster":{"label":1}}

        $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($data['address']);

        if(is_null($data['cluster']['label'])){
            $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel(1);
            if(is_null($cluster)){
                $cluster = new Cluster();
                $cluster->setLabel(1);
                $em->persist($cluster);
                $em->flush();
            }
        } else {
            $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($data['cluster']['label']);
            if(is_null($cluster)){
                $cluster = new Cluster();
                $cluster->setLabel($data['cluster']['label']);
                $em->persist($cluster);
                $em->flush();
            }
        }

        if(is_null($luminaire)) {
            $luminaire = new Luminaire();
            $luminaire->setAddress($data['address']);
            $luminaire->setSerial($data['serial']);
            $luminaire->setLigne($data['ligne']);
            $luminaire->setColonne($data['colonne']);
            $luminaire->setCluster($cluster);
            $em->persist($luminaire);
            $em->flush();


            if($_SERVER['APP_ENV'] == 'prod') {
                // Initialiser master/slave
                // $spots = implode(" ", $data['found']);

                // Interroger le réseau de luminaires
                // !!!TODO!!! --address + liste des luminaires
                $args = ' -a '.$data['address'].' --get-info specs --quiet --json';
                $success_msg = 'Data from '.$data['address'].'successfully retrieved !';
                $error_msg = 'For a unknown reason, data from '.$data['address'].'were not retrieved !';

                $result = $lumiatec->sendCmd($args, $success_msg, $error_msg);

            } 

            $data = json_decode($result['output'], TRUE);

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

        } else {
            $luminaire->setLigne($data['ligne']);
            $luminaire->setColonne($data['colonne']);
            $luminaire->setCluster($cluster);
        }

        $status_on = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findOneByCode(0);
        $status_off = $this->getDoctrine()->getRepository(LuminaireStatus::class)->findOneByCode(99);
        $luminaire->removeStatus($status_off);
        $luminaire->addStatus($status_on);

        $em->flush();

        return new Response(
            'Lighting '.$luminaire->getAddress().' correctly linked',
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        ); 
    }

    /**
     * @Route("/remote/luminaire/unlink", name="unlink-luminaire-from-remote")
     */
    public function unlinkLuminaireFromRemote(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $data = json_decode($request->getContent(), true);

        //data: {"address":30,"serial":"0x12B0001E","ligne":2,"colonne":3,"cluster":{"label":1}}

        $luminaire = $this->getDoctrine()->getRepository(Luminaire::class)->findOneByAddress($data['address']);

        if(!is_null($luminaire)){
            $em->remove($luminaire);
            $em->flush();

            return new Response(
                'Lighting '.$luminaire->getAddress().' correctly unlinked',
                Response::HTTP_OK,
                ['content-type' => 'text/html']
            );

        } else {
            return new Response(
                $data['address'].' > Lighting not correctly unlinked',
                Response::HTTP_NO_CONTENT,
                ['content-type' => 'text/html']
            );
        } 
    }

    /**
     * @Route("/remote/logs", name="logs-from-remote")
     */
    public function logsFromRemote(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        // dd($data);

        $em = $this->getDoctrine()->getManager();

        $logs = $this->getDoctrine()->getRepository(Log::class)->findLogsOlderThan($data['date']);

        // $encoders = [new XmlEncoder(), new JsonEncoder()];
        // $normalizers = [new ObjectNormalizer()];
        // $serializer = new Serializer($normalizers, $encoders);

        // $jsonContent = $serializer->serialize($logs, 'json');

        $response = new JsonResponse();
        $response->setData($logs);
        // $response->headers->set('Content-Type', 'application/json');

        return $response;

        // return new Response(
        //     $logs,
        //     Response::HTTP_OK,
        //     ['content-type' => 'text/html']
        // );

        
    }
}
