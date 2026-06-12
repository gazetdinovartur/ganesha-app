<?php

namespace App\Repository;

use App\Entity\MenuDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MenuDay> */
class MenuDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuDay::class);
    }

    /**
     * @return list<MenuDay>
     */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.date BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<MenuDay>
     */
    public function findPublishedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.dishes', 'md')->addSelect('md')
            ->leftJoin('md.dish', 'd')->addSelect('d')
            ->leftJoin('d.category', 'c')->addSelect('c')
            ->andWhere('m.date BETWEEN :from AND :to')
            ->andWhere('m.isPublished = true')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.date', 'ASC')
            ->addOrderBy('md.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
