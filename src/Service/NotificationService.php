<?php

namespace App\Service;

use App\Entity\Order;
use Psr\Log\LoggerInterface;

final class NotificationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function orderPaid(Order $order, ?string $externalPaymentId = null): void
    {
        $this->logger->info('Order paid notification', [
            'order_uuid' => (string) $order->getUuid(),
            'human_number' => $order->getHumanNumber(),
            'customer_phone' => $order->getCustomer()?->getPhone(),
            'channel' => $order->getChannel()->value,
            'total_amount' => $order->getTotalAmount(),
            'external_payment_id' => $externalPaymentId,
        ]);
    }
}
