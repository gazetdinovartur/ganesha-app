<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Order> */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function getNextHumanNumber(): int
    {
        $result = $this->createQueryBuilder('o')
            ->select('MAX(o.humanNumber)')
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $result) + 1;
    }

    public function findOneByUuid(string $uuid): ?Order
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        return $this->createOrderDetailsQueryBuilder()
            ->andWhere('o.uuid = :uuid')
            ->setParameter('uuid', Uuid::fromString($uuid), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByRepeatToken(string $repeatToken): ?Order
    {
        $repeatToken = trim($repeatToken);
        if ($repeatToken === '') {
            return null;
        }

        return $this->createOrderDetailsQueryBuilder()
            ->andWhere('o.repeatToken = :token')
            ->setParameter('token', $repeatToken)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createOrderDetailsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')->addSelect('c')
            ->leftJoin('o.items', 'i')->addSelect('i')
            ->leftJoin('o.pickupPoint', 'p')->addSelect('p');
    }

    /**
     * @return list<Order>
     */
    public function findByPaymentGroupUuid(Uuid $paymentGroupUuid): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')->addSelect('c')
            ->leftJoin('o.items', 'i')->addSelect('i')
            ->andWhere('o.paymentGroupUuid = :group')
            ->setParameter('group', $paymentGroupUuid, 'uuid')
            ->orderBy('o.pickupDate', 'ASC')
            ->addOrderBy('o.humanNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Order>
     */
    public function findByPickupDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')->addSelect('c')
            ->leftJoin('o.items', 'i')->addSelect('i')
            ->andWhere('o.pickupDate = :date')
            ->setParameter('date', $date)
            ->orderBy('o.humanNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Order>
     */
    public function findByPickupDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')->addSelect('c')
            ->leftJoin('o.items', 'i')->addSelect('i')
            ->andWhere('o.pickupDate >= :from')
            ->andWhere('o.pickupDate <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.pickupDate', 'ASC')
            ->addOrderBy('o.humanNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
