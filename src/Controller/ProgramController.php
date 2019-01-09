<?php

namespace App\Controller;


use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

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
use App\Entity\Cluster;
use App\Entity\Recipe;


use App\Form\ProgramType;
use App\Form\StepType;
use App\Form\RunType;

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
            'navtitle' => 'Programs', 
        ]);
    }

    /**
     * @Route("/program/new", name="new-program")
     */
    public function newProgram(Request $request)
    {
    	$program = new Program;
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
            'navtitle' => 'New Program',
        ]);
    }

     /**
     * @Route("/program/edit/{id}", name="edit-program")
     */
    public function editProgram(Request $request, Program $program)
    {
    	$em = $this->getDoctrine()->getManager();

	   	$originalSteps = new ArrayCollection();

	    // Create an ArrayCollection of the current Tag objects in the database
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

            return $this->redirectToRoute('program');
        }
        return $this->render('program/new-program.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'Edit Program',
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
        $runs = $this->getDoctrine()->getRepository(Run::class)->findAll();

        return $this->render('control/runs.html.twig', [
            'controller_name' => 'ProgramController',
            'runs' => $runs,
            'navtitle' => 'Runs', 
        ]);
    }

    /**
     * @Route("/run/new/{id}", name="new-run")
     */
    public function newRun(Request $request, Cluster $cluster)
    {
        $em = $this->getDoctrine()->getManager();

        $run = new Run;
        $run->setCluster($cluster);
        $form = $this->createForm(RunType::class, $run);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $em->persist($run);
            $em->flush();

            $process = new Process('./bin/velire.sh --run '.$data->getId());
            $process->run();

            return $this->redirectToRoute('update-log');
        }
        return $this->render('control/new-run.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'New Run',
        ]);
    }

    /**
     * @Route("/play/new/{id}", name="new-play")
     */
    public function newPlay(Request $request, Cluster $cluster)
    {
        $session = new Session;
        
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

            $process = new Process('python3 ./bin/velire-cmd.py -e --config ./bin/config.yaml --input ./bin/config.json --cluster '.$cluster->getId().' --play '.$recipe->getId());
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
            return $this->redirectToRoute('update-log');        
        }

        return $this->render('control/new-play.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'New Play',
        ]);
    }

    /**
     * @Route("/run/delete/{id}", name="delete-run")
     */
    public function deleteRun(Request $request, Run $run)
    {
        $em = $this->getDoctrine()->getManager();


        $process = new Process('./bin/velire.sh --delete-run'.$run->getCluster()->getId());
        $process->run();

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

        $form = $this->createForm(RunType::class, $run);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            
            $em->flush();
            
            $process = new Process('./bin/velire.sh --run '.$run->getId());
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            

            $date = new \DateTime($process->getOutput());
            $run->setDateEnd($date);

            $em->persist($run);
            $em->flush();
            

            return $this->redirectToRoute('run');
        }
        return $this->render('control/new-run.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
            'navtitle' => 'Edit Run',
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

            $process = new Process('python3 ./bin/velire-cmd.py -e --config ./bin/config.yaml --input ./bin/config.json --cluster '.$cluster_id.' --play '.$recipe->getId());
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
}
