<?php

namespace App\Repository;

use App\Entity\ServerCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ServerCheck|null find($id, $lockMode = null, $lockVersion = null)
 * @method ServerCheck|null findOneBy(array $criteria, array $orderBy = null)
 * @method ServerCheck[]    findAll()
 * @method ServerCheck[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServerCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerCheck::class);
    }

    // /**
    //  * @return ServerCheck[] Returns an array of ServerCheck objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ServerCheck
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
