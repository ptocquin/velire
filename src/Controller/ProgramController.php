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
        ]);
    }

    /**
     * @Route("/run/new", name="new-run")
     */
    public function newRun(Request $request)
    {
        $run = new Run;
        $form = $this->createForm(RunType::class, $run);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($run);
            $em->flush();

            $process = new Process('./bin/run.R');
            $process->run();

            return $this->redirectToRoute('run');
        }
        return $this->render('control/new-run.html.twig', [
            'controller_name' => 'ProgramController',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/run/delete/{id}", name="delete-run")
     */
    public function deleteRun(Request $request, Run $run)
    {
        $em = $this->getDoctrine()->getManager();

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
            
            $process = new Process('./bin/run.R');
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

            $process = new Process('./bin/play.R '.$cluster_id.' '.$recipe->getId());
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
        ]);
    }
}
