<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

final class OrderStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function markAsPaid(Order $order): void
    {
        if ($order->getStatus() === OrderStatus::Cancelled || $order->getStatus() === OrderStatus::Paid) {
            return;
        }

        $this->applyStatus($order, OrderStatus::Paid);
        $this->entityManager->flush();
    }

    public function updateStatus(Order $order, OrderStatus $status): void
    {
        $this->applyStatus($order, $status);
        $this->entityManager->flush();
    }

    /**
     * @param list<int> $orderIds
     */
    public function batchUpdateStatus(array $orderIds, OrderStatus $status): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $orders = $this->entityManager->getRepository(Order::class)->findBy(['id' => $orderIds]);
        foreach ($orders as $order) {
            $this->applyStatus($order, $status);
        }

        $this->entityManager->flush();

        return count($orders);
    }

    public function batchUpdateStatusForDate(
        \DateTimeImmutable $date,
        OrderStatus $from,
        OrderStatus $to,
    ): int {
        $orders = $this->entityManager->getRepository(Order::class)->findBy([
            'pickupDate' => $date,
            'status' => $from,
        ]);

        foreach ($orders as $order) {
            $this->applyStatus($order, $to);
        }

        $this->entityManager->flush();

        return count($orders);
    }

    private function applyStatus(Order $order, OrderStatus $status): void
    {
        $order->setStatus($status);

        if ($status === OrderStatus::Paid && $order->getPaidAt() === null) {
            $order->setPaidAt(new \DateTimeImmutable());
        }
    }
}
