<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

final class OrderStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
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
        $previous = $order->getStatus();
        $this->applyStatus($order, $status);
        $this->entityManager->flush();
        $this->notifyIfNeeded($order, $previous, $status);
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
            $previous = $order->getStatus();
            $this->applyStatus($order, $status);
            $this->notifyIfNeeded($order, $previous, $status);
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
            $previous = $order->getStatus();
            $this->applyStatus($order, $to);
            $this->notifyIfNeeded($order, $previous, $to);
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

    private function notifyIfNeeded(Order $order, OrderStatus $previous, OrderStatus $new): void
    {
        if ($previous === $new) {
            return;
        }

        match ($new) {
            OrderStatus::Ready => $this->notificationService->orderReady($order),
            OrderStatus::Completed => $this->notificationService->orderCompleted($order),
            default => null,
        };
    }
}
