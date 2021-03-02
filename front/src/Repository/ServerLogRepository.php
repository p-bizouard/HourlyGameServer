<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\ServerLog;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints\Date;

/**
 * @method ServerLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ServerLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method ServerLog[]    findAll()
 * @method ServerLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServerLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServerLog::class);
    }

    // /**
    //  * @return ServerLog[] Returns an array of ServerLog objects
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
    public function findOneBySomeField($value): ?ServerLog
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
    
    public function findLastLogs(Server $server, DateTime $date)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.server = :server')
            ->andWhere('s.created >= :date')
            ->setParameter('server', $server)
            ->setParameter('date', $date)
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
