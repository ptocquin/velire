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

use App\Entity\Program;
use App\Entity\Step;
use App\Entity\Run;
use App\Entity\RunStep;
use App\Entity\Cluster;
use App\Entity\Recipe;
use App\Entity\Luminaire;
use App\Entity\Ingredient;
use App\Entity\Led;

use App\Form\ProgramType;
use App\Form\StepType;
use App\Form\RunType;
use App\Form\RunEditType;


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
    public function newRun(Request $request, Cluster $cluster)
    {
        $em = $this->getDoctrine()->getManager();

        $run = new Run;
        $uuid = uuid_create(UUID_TYPE_RANDOM);
        $run->setUuid($uuid);
        $run->setCluster($cluster);
        $form = $this->createForm(RunType::class, $run);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $em->persist($run);
            $em->flush();

            # Fetch lightings addresses
            $luminaires = $cluster->getLuminaires();
            $list = " --address ";
            foreach ($luminaires as $luminaire) {
                $list .= $luminaire->getAddress()." ";
            }

            # Fetch Steps
            $program = $run->getProgram();
            $steps = $program->getSteps();
            # Start
            $start = $run->getStart();
            $goto = -1;
            $step_index = 0;

            while ($step_index < count($steps)) {
                // die(print_r($steps[$step_index]->getType()));
                $step = $steps[$step_index];
                $type = $step->getType();

                switch ($type) {
                    case "time":
                        list($hours, $minutes) = explode(':', $step->getValue(), 2);
                        $step_duration = $minutes * 60 + $hours * 3600;
                        $commands = [];
                        $ingredients = $step->getRecipe()->getIngredients();
                        foreach ($ingredients as $ingredient) {
                            $level = $ingredient->getLevel();
                            $led = $ingredient->getLed();
                            $color = $led->getType()."_".$led->getWavelength();
                            $commands[] = $color." ".$level;
                        }
                        $cmd = $this->getParameter('app.velire_cmd').$list." --exclusive --set-power 1 --set-colors ".implode(" ", $commands);
                        $start = $start->add(new \DateInterval('PT'.$step_duration.'S'));
                        $new_step = new RunStep();
                        $new_step->setRun($run);
                        $new_step->setStart($start);
                        $new_step->setCommand($cmd);
                        $new_step->setStatus(0);
                        $em->persist($new_step);
                        $em->flush();
                        $step_index = $step_index + 1;
                        // die(print_r($start));
                        break;
                    case "off":
                        list($hours, $minutes) = explode(':', $step->getValue(), 2);
                        $step_duration = $minutes * 60 + $hours * 3600;
                        $cmd = $this->getParameter('app.velire_cmd').$list." --shutdown";
                        $start = $start->add(new \DateInterval('PT'.$step_duration.'S'));
                        $new_step = new RunStep();
                        $new_step->setRun($run);
                        $new_step->setStart($start);
                        $new_step->setCommand($cmd);
                        $new_step->setStatus(0);
                        $em->persist($new_step);
                        $em->flush();
                        $step_index = $step_index + 1;
                        // die(print_r($cmd));
                        break;
                    case "goto":
                        list($s, $n) = explode(':', $step->getValue(), 2);
                        if($goto < 0){
                            $goto = $n;
                        } elseif ($goto == 0) {
                            $goto = -1;
                            $step_index = $step_index + 1;
                        } elseif ($goto > 0) {
                            $step_index = $s;
                            $goto = $goto - 1;
                        }
                        break;
                }
            }

            $run->setDateEnd($start);
            $em->persist($run);
            $em->flush();            
            

            // // !!! TODO !!! à reprendre dans la lib bash ?
            // $process = new Process($this->getParameter('app.bash_cmd').' --run '.$data->getId());
            // $process->run();

            // // executes after the command finishes
            // if (!$process->isSuccessful()) {
            //     // throw new ProcessFailedException($process);
            //         // add flash messages
            //         $this->addFlash(
            //             'error',
            //             'For a unknown reason, the run was not started'
            //         );
            // }

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
    public function newPlay(Request $request, Cluster $cluster)
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
            // $recipe = $this->getDoctrine()->getRepository(Recipe::class)->find($r);
            $commands = [];
            $ingredients = $recipe->getIngredients();
            foreach ($ingredients as $ingredient) {
                $level = $ingredient->getLevel();
                $led = $ingredient->getLed();
                $color = $led->getType()."_".$led->getWavelength();
                $commands[] = $color." ".$level;
            }

            // die(print_r($commands));

            $luminaires = $cluster->getLuminaires();
            $list = " --address ";
            foreach ($luminaires as $luminaire) {
                $list .= $luminaire->getAddress()." ";
            }

            // Utiliser les info dans la base de données + set-colors
            // !!! TODO !!!
            $process = new Process($this->getParameter('app.velire_cmd').$list.' --exclusive --set-power 1 --set-colors '.implode(" ", $commands));
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                //throw new ProcessFailedException($process);
                $this->addFlash(
                        'error',
                        'For a unknown reason, the recipe was not started'
                    );
            } else {
                            // add flash messages
                $this->addFlash(
                    'info',
                    // $process->getOutput()
                    'Recipe '.$recipe->getLabel().' successfully started on cluster '.$cluster->getLabel()
                );
            }
            return $this->redirectToRoute('update-log');        
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
    public function editRun(Request $request, Run $run)
    {
        $em = $this->getDoctrine()->getManager();

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

            // !!! TODO !!! à reprendre dans la lib bash ?
            $process = new Process($this->getParameter('app.velire_cmd').' -e --input '.$this->getParameter('app.shared_dir').'/config.json --set-run '.$run->getId());
            $process->run();

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
    public function playFromRemote(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        $em = $this->getDoctrine()->getManager();

        $recipe = $this->getDoctrine()->getRepository(Recipe::class)->findOneByUuid($data['recipe']['uuid']);

        if(is_null($recipe)){
            $recipe = new Recipe;
            $recipe->setUuid($data['recipe']['uuid']);
            $recipe->setLabel($data['recipe']['label']);
            $recipe->setDescription($data['recipe']['description']);
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
            $msg = "not null";
        }

        $commands = [];
        $ingredients = $recipe->getIngredients();
        foreach ($ingredients as $ingredient) {
            $level = $ingredient->getLevel();
            $led = $ingredient->getLed();
            $color = $led->getType()."_".$led->getWavelength();
            $commands[] = $color." ".$level;
        }

        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($data['cluster']);

        $luminaires = $cluster->getLuminaires();
        $list = " --address ";
        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
        }

        // Utiliser les info dans la base de données + set-colors
        // !!! TODO !!!
        $process = new Process($this->getParameter('app.velire_cmd').$list.' --exclusive --set-power 1 --set-colors '.implode(" ", $commands));
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            //throw new ProcessFailedException($process);
            $this->addFlash(
                    'error',
                    'For a unknown reason, the recipe was not started'
                );
        } else {
                        // add flash messages
            $this->addFlash(
                'info',
                // $process->getOutput()
                'Recipe '.$recipe->getLabel().' successfully started on cluster '.$cluster->getLabel()
            );
        }

        return new Response(
            'Recipe '.$recipe->getLabel().' successfully started on cluster '.$cluster->getLabel(),
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/remote/shutdown", name="shutdown-from-remote")
     */
    public function shutdownFromRemote(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        $cluster = $this->getDoctrine()->getRepository(Cluster::class)->findOneByLabel($data['cluster']);

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

        return new Response(
            'Cluster '.$cluster->getLabel().' has been switched off.',
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/remote/run", name="run-from-remote")
     */
    public function runFromRemote(Request $request)
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

        # Fetch lightings addresses
        $luminaires = $cluster->getLuminaires();
        $list = " --address ";
        foreach ($luminaires as $luminaire) {
            $list .= $luminaire->getAddress()." ";
        }

        # Fetch Steps
        $steps = $program->getSteps();
        # Start
        $start = $now;
        $goto = -1;
        $step_index = 0;

        while ($step_index < count($steps)) {
            // die(print_r($steps[$step_index]->getType()));
            $step = $steps[$step_index];
            $type = $step->getType();

            switch ($type) {
                case "time":
                    list($hours, $minutes) = explode(':', $step->getValue(), 2);
                    $step_duration = $minutes * 60 + $hours * 3600;
                    $commands = [];
                    $ingredients = $step->getRecipe()->getIngredients();
                    foreach ($ingredients as $ingredient) {
                        $level = $ingredient->getLevel();
                        $led = $ingredient->getLed();
                        $color = $led->getType()."_".$led->getWavelength();
                        $commands[] = $color." ".$level;
                    }
                    $cmd = $this->getParameter('app.velire_cmd').$list." --exclusive --set-power 1 --set-colors ".implode(" ", $commands);
                    $start = $start->add(new \DateInterval('PT'.$step_duration.'S'));
                    $new_step = new RunStep();
                    $new_step->setRun($run);
                    $new_step->setStart($start);
                    $new_step->setCommand($cmd);
                    $new_step->setStatus(0);
                    $em->persist($new_step);
                    $em->flush();
                    $step_index = $step_index + 1;
                    // die(print_r($start));
                    break;
                case "off":
                    list($hours, $minutes) = explode(':', $step->getValue(), 2);
                    $step_duration = $minutes * 60 + $hours * 3600;
                    $cmd = $this->getParameter('app.velire_cmd').$list." --shutdown";
                    $start = $start->add(new \DateInterval('PT'.$step_duration.'S'));
                    $new_step = new RunStep();
                    $new_step->setRun($run);
                    $new_step->setStart($start);
                    $new_step->setCommand($cmd);
                    $new_step->setStatus(0);
                    $em->persist($new_step);
                    $em->flush();
                    $step_index = $step_index + 1;
                    // die(print_r($cmd));
                    break;
                case "goto":
                    list($s, $n) = explode(':', $step->getValue(), 2);
                    if($goto < 0){
                        $goto = $n;
                    } elseif ($goto == 0) {
                        $goto = -1;
                        $step_index = $step_index + 1;
                    } elseif ($goto > 0) {
                        $step_index = $s;
                        $goto = $goto - 1;
                    }
                    break;
            }
        }

        $run->setDateEnd($start);
        $em->persist($run);
        $em->flush(); 

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
}
