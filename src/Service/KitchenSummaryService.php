<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;

final class KitchenSummaryService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
    }

    /**
     * @return list<Order>
     */
    public function getOrdersForDate(\DateTimeImmutable $date): array
    {
        return $this->orderRepository->findByPickupDate($date);
    }

    /**
     * @return list<Order>
     */
    public function getOrdersForDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->orderRepository->findByPickupDateRange($from, $to);
    }

    /**
     * @param list<Order> $orders
     *
     * @return array<string, int> dish name => total portions
     */
    public function buildPortionSummary(array $orders, ?OrderStatus $status = null): array
    {
        $summary = [];

        foreach ($orders as $order) {
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
