<?php

namespace App\Repository;

use App\Entity\DishCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DishCategory> */
class DishCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DishCategory::class);
    }

    public function findOneByNameInsensitive(string $name): ?DishCategory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = LOWER(:name)')
            ->setParameter('name', trim($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function nextSortOrder(): int
    {
        $max = $this->createQueryBuilder('c')
            ->select('MAX(c.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
