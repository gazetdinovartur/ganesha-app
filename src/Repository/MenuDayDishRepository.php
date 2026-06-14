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
        $indexed = $this->findByIdsForOrdering([$id]);

        return $indexed[$id] ?? null;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, MenuDayDish>
     */
    public function findByIdsForOrdering(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<MenuDayDish> $rows */
        $rows = $this->createQueryBuilder('md')
            ->innerJoin('md.menuDay', 'm')->addSelect('m')
            ->innerJoin('md.dish', 'd')->addSelect('d')
            ->andWhere('md.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $id = $row->getId();
            if ($id !== null) {
                $indexed[$id] = $row;
            }
        }

        return $indexed;
    }
}
