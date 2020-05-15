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
    
    public function findConnectedLuminaire()
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.status', 's')
            ->andWhere('s.code < :val')
            ->setParameter('val', 99)
            // ->orderBy('l.id', 'ASC')
            // ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }

   /**
    * @return Luminaire[] Returns an array of Luminaire objects
    */
    
    public function getAll()
    {
        return $this->createQueryBuilder('l')
            // ->leftJoin('l.status', 's')
            // ->andWhere('s.code < :val')
            // ->setParameter('val', 99)
            // ->orderBy('l.id', 'ASC')
            // ->setMaxResults(10)
            ->select(array('l.serial', 'l.address'))
            ->getQuery()
            ->getResult()
        ;
    }
    

    
    public function getByXY($x, $y): ?Luminaire
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.ligne = :y')
            ->andWhere('l.colonne = :x')
            ->setParameter('y', $y)
            ->setParameter('x', $x)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function getNotMapped()
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.status', 's')
            ->andWhere('l.ligne is null')
            ->andWhere('l.colonne is null')
            ->andWhere('s.code < :val')
            ->setParameter('val', 99)
            ->getQuery()
            ->getResult()
        ;
    }
    

    public function getXMax()
    {
        $qb = $this->createQueryBuilder('l');
        $qb->select('MAX(l.colonne) as x_max');  
        return $qb->getQuery()->getSingleResult();
    }


    public function getYMax()
    {
        $qb = $this->createQueryBuilder('l');
        $qb->select('MAX(l.ligne) as y_max');  
        return $qb->getQuery()->getSingleResult();
    }
}
