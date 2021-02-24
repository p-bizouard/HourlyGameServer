<?php

namespace App\Repository;

use App\Entity\ServerHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ServerHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ServerHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ServerHistory[]    findAll()
 * @method ServerHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServerHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerHistory::class);
    }

    // /**
    //  * @return ServerHistory[] Returns an array of ServerHistory objects
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
    public function findOneBySomeField($value): ?ServerHistory
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
