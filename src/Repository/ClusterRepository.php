<?php

namespace App\Repository;

use App\Entity\Cluster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Cluster|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cluster|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cluster[]    findAll()
 * @method Cluster[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClusterRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Cluster::class);
    }

   /**
    * @return Cluster[] Returns an array of Cluster objects
    */
    
    public function getEmptyClusters()
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.luminaires', 'l')
            ->where('l.cluster is null')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
    * @return Cluster[] Returns an array of Cluster objects
    */
    
    public function getRunningClusters($id, $today)
    {
        $qb = $this->createQueryBuilder('c');
        
        $qb->leftJoin('c.runs', 'r')
            ->where('c.id = :id')
            ->andWhere('r.start <= :today')
            ->andWhere('r.date_end >= :today')
            // ->andWhere('r.status is null')
            // ->orWhere('r.status <> :status')
            // ->andWhere($qb->expr()->neq('r.status', ':status'))
            ->setParameter('id', $id)
            ->setParameter('today', $today)
            // ->setParameter('status', 'pause')
            ;

        return $qb
            ->getQuery()
            ->getResult()
            ;


        // return $this->createQueryBuilder('c')
        //     ->leftJoin('c.runs', 'r')
        //     ->where('c.id = :id')
        //     ->andWhere('r.start <= :today')
        //     ->andWhere('r.date_end >= :today')
        //     ->andWhere($expr->neq('r.status', 'pause'))
        //     ->setParameter('id', $id)
        //     ->setParameter('today', $today)
        //     ->getQuery()
        //     ->getResult()
        // ;
    }

   /**
    * @return Cluster[] Returns an array of Cluster objects
    */
    
    public function getLedTypes($id)
    {
        return $this->createQueryBuilder('cl')
            ->leftJoin('cl.luminaires', 'l')
            ->leftJoin('l.channels', 'ch')
            ->leftJoin('ch.led', 'le')
            ->groupBy('le.type')
            ->addGroupBy('le.wavelength')
            ->where('cl.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;
    }

    public function getMaxCluster()
    {
        $qb = $this->createQueryBuilder('cl');
        $qb->select('MAX(cl.label) as cl_max');  
        return $qb->getQuery()->getSingleResult();
    }
    

    /*
    public function findOneBySomeField($value): ?Cluster
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
