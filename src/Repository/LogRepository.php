<?php

namespace App\Repository;

use App\Entity\Log;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Log|null find($id, $lockMode = null, $lockVersion = null)
 * @method Log|null findOneBy(array $criteria, array $orderBy = null)
 * @method Log[]    findAll()
 * @method Log[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Log::class);
    }

   /**
    * @return Log[] Returns an array of Log objects
    */
    
    public function getClusterInfo($cluster, $n)
    {
        return $this->createQueryBuilder('l')
            ->where('l.cluster = :cluster')
            ->andWhere('l.type = :type')
            ->setParameter('cluster', $cluster)
            ->setParameter('type', 'cluster_info')
            ->orderBy('l.time', 'DESC')
            ->setMaxResults($n)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return Log[] Returns an array of Log objects
    */
    
    public function getLastLog()
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.type = :type')
            ->setParameter('type', 'cluster_info')
            ->orderBy('l.time', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return Log[] Returns an array of Log objects
    */
    
    public function getClusterLastLog($cluster)
    {
        return $this->createQueryBuilder('l')
            ->where('l.cluster = :cluster')
            ->andWhere('l.type = :type')
            ->setParameter('cluster', $cluster)
            ->setParameter('type', 'cluster_info')
            ->orderBy('l.time', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }




   /**
    * @return Log[] Returns an array of Log objects
    */
    
    public function getLuminairesInfo($cluster, $time)
    {
        return $this->createQueryBuilder('l')
            ->where('l.cluster = :cluster')
            ->andWhere('l.type = :type')
            ->andWhere('l.time = :time')
            ->setParameter('cluster', $cluster)
            ->setParameter('type', 'luminaire_info')
             ->setParameter('time', $time)
            ->getQuery()
            ->getResult()
        ;
    }
    

    /*
    public function findOneBySomeField($value): ?Log
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
