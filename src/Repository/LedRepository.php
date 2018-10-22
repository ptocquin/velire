<?php

namespace App\Repository;

use App\Entity\Led;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Led|null find($id, $lockMode = null, $lockVersion = null)
 * @method Led|null findOneBy(array $criteria, array $orderBy = null)
 * @method Led[]    findAll()
 * @method Led[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LedRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Led::class);
    }

   /**
    * @return Led[] Returns an array of Led objects
    */
    
    public function getLedTypesFromCluster($id)
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.channels', 'ch')
            ->leftJoin('ch.luminaire', 'lu')
            ->leftJoin('lu.cluster', 'c')
            ->groupBy('l.type')
            ->addGroupBy('l.wavelength')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return Led[] Returns an array of Led objects
    */
    
    public function getLedTypesFromRecipe($id)
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.ingredients', 'i')
            ->leftJoin('i.recipe', 'r')
            ->groupBy('l.type')
            ->addGroupBy('l.wavelength')
            ->where('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;
    }
    

    /*
    public function findOneBySomeField($value): ?Led
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
