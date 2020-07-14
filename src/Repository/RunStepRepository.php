<?php

namespace App\Repository;

use App\Entity\RunStep;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method RunStep|null find($id, $lockMode = null, $lockVersion = null)
 * @method RunStep|null findOneBy(array $criteria, array $orderBy = null)
 * @method RunStep[]    findAll()
 * @method RunStep[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RunStepRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, RunStep::class);
    }

   /**
    * @return RunStep[] Returns an array of RunStep objects
    */
    
    public function findStepToRun($time)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.start = :val')
            ->setParameter('val', $time)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return RunStep[] Returns an array of RunStep objects
    */
    
    public function getLastSteps($run)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.run = :run')
            ->andWhere('r.status = 1')
            ->setParameter('run', $run)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return RunStep[] Returns an array of RunStep objects
    */
    
    public function getLastStep($run)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.run = :run')
            ->andWhere('r.status = 1')
            ->setParameter('run', $run)
            ->orderBy('r.start', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return RunStep[] Returns an array of RunStep objects
    */
    
    public function getNonExecutedSteps($run, $time)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.run = :run')
            ->andWhere('r.status = 0')
            ->andWhere('r.start < :time')
            ->setParameter('time', $time)
            ->setParameter('run', $run)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return RunStep[] Returns an array of RunStep objects
    */
    
    public function getLastNonExecutedStep($run, $time)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.run = :run')
            ->andWhere('r.status = 0')
            ->andWhere('r.start < :time')
            ->setParameter('time', $time)
            ->setParameter('run', $run)
            ->orderBy('r.start', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }
    

    /*
    public function findOneBySomeField($value): ?RunStep
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
