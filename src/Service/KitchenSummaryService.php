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
     * @return array<string, int> dish name => total portions
     */
    public function getPortionSummary(\DateTimeImmutable $date, ?OrderStatus $status = null): array
    {
        return $this->orderRepository->getPortionSummaryForDate($date, $status);
    }
}
