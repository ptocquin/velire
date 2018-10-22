<?php

namespace App\Repository;

use App\Entity\Pcb;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Pcb|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pcb|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pcb[]    findAll()
 * @method Pcb[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PcbRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Pcb::class);
    }

//    /**
//     * @return Pcb[] Returns an array of Pcb objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Pcb
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
