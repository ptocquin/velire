<?php

namespace App\Repository;

use App\Entity\Run;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Run|null find($id, $lockMode = null, $lockVersion = null)
 * @method Run|null findOneBy(array $criteria, array $orderBy = null)
 * @method Run[]    findAll()
 * @method Run[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RunRepository extends ServiceEntityRepository
{

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Run::class);
    }

   /**
    * @return Run[] Returns an array of Run objects
    */
    
    public function getRunningRuns($time)
    {
        // $today = new \DateTime();
        return $this->createQueryBuilder('r')
            ->where('r.start <= :today')
            ->andWhere('r.dateend >= :today')
            ->setParameter('today', $time)
            ->orderBy('r.start', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return Run[] Returns an array of Run objects
    */
    
    public function getRunningRunsForCluster($id)
    {
        $today = new \DateTime();
        return $this->createQueryBuilder('r')
            ->where('r.start <= :today')
            ->andWhere('r.dateend >= :today')
            ->andWhere('r.cluster = :id')
            ->setParameter('today', $today)
            ->setParameter('id', $id)
            ->orderBy('r.start', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return Run[] Returns an array of Run objects
    */
    
    public function getComingRuns($time)
    {
        // $today = new \DateTime();
        return $this->createQueryBuilder('r')
            ->where('r.start > :today')
            ->setParameter('today', $time)
            ->orderBy('r.start', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return Run[] Returns an array of Run objects
    */
    
    public function getPastRuns($time)
    {
        // $today = new \DateTime();
        // $today = date('Y-m-d H:i:00');
        return $this->createQueryBuilder('r')
            ->where('r.dateend < :today')
            ->setParameter('today', $time)
            ->orderBy('r.start', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
    

    /*
    public function findOneBySomeField($value): ?Run
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
