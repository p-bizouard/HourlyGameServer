<?php

namespace App\Repository;

use App\Entity\ServerBackup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ServerBackup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ServerBackup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ServerBackup[]    findAll()
 * @method ServerBackup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServerBackupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerBackup::class);
    }

    // /**
    //  * @return ServerBackup[] Returns an array of ServerBackup objects
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
    public function findOneBySomeField($value): ?ServerBackup
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
