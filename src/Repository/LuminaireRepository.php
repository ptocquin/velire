<?php

namespace App\Repository;

use App\Entity\Luminaire;
use App\Entity\LuminaireStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Luminaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Luminaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Luminaire[]    findAll()
 * @method Luminaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LuminaireRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Luminaire::class);
    }

   /**
    * @return Luminaire[] Returns an array of Luminaire objects
    */
    
    public function findInstalledLuminaire()
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.status', 's')
            ->andWhere('s.code < :val')
            ->setParameter('val', 99)
            ->orderBy('l.id', 'ASC')
            // ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    

    /*
    public function findOneBySomeField($value): ?Luminaire
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
