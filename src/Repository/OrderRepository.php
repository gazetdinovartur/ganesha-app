<?php

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

        return $this->findOneBy(['uuid' => Uuid::fromString($uuid)]);
    }

    public function findOneByRepeatToken(string $repeatToken): ?Order
    {
        $repeatToken = trim($repeatToken);
        if ($repeatToken === '') {
            return null;
        }

        return $this->findOneBy(['repeatToken' => $repeatToken]);
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
     * @return array<string, int>
     */
    public function getPortionSummaryForDate(\DateTimeImmutable $date, ?OrderStatus $status = null): array
    {
        $summary = [];

        foreach ($this->findByPickupDate($date) as $order) {
            if ($status !== null && $order->getStatus() !== $status) {
                continue;
            }

            foreach ($order->getItems() as $item) {
                $snapshot = $item->getDishSnapshot();
                $name = (string) ($snapshot['name'] ?? 'Блюдо');
                $summary[$name] = ($summary[$name] ?? 0) + $item->getQuantity();
            }
        }

        ksort($summary);

        return $summary;
    }
}
