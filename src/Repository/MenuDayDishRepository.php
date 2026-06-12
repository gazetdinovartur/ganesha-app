<?php

namespace App\Repository;

use App\Entity\MenuDayDish;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MenuDayDish> */
class MenuDayDishRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuDayDish::class);
    }

    public function findOneForOrdering(int $id): ?MenuDayDish
    {
        return $this->createQueryBuilder('md')
            ->innerJoin('md.menuDay', 'm')->addSelect('m')
            ->innerJoin('md.dish', 'd')->addSelect('d')
            ->andWhere('md.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
