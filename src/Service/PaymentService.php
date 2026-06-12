<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Exception\PaymentConfirmationException;
use App\Repository\OrderRepository;
use Symfony\Component\Uid\Uuid;

final class PaymentService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderStatusService $orderStatusService,
    ) {
    }

    /**
     * Подтверждает оплату заказа по webhook/API провайдера (СБП, банк, агрегатор).
     *
     * Идемпотентно: повторный вызов для уже оплаченного заказа возвращает заказ без ошибки.
     *
     * @throws PaymentConfirmationException
     */
    public function confirmPayment(
        string $orderUuid,
        ?int $amountKopecks = null,
    ): Order {
        if (!Uuid::isValid($orderUuid)) {
            throw new PaymentConfirmationException('Некорректный order_uuid.', 400, 'invalid_uuid');
        }

        $order = $this->orderRepository->findOneByUuid($orderUuid);
        if ($order === null) {
            throw new PaymentConfirmationException('Заказ не найден.', 404, 'order_not_found');
        }

        if ($order->getStatus() === OrderStatus::Paid) {
            return $order;
        }

        if ($order->getStatus() === OrderStatus::Cancelled) {
            throw new PaymentConfirmationException('Заказ отменён, оплату принять нельзя.', 409, 'order_cancelled');
        }

        if ($amountKopecks !== null && $amountKopecks !== $order->getTotalAmount()) {
            throw new PaymentConfirmationException('Сумма платежа не совпадает с заказом.', 422, 'amount_mismatch');
        }

        $this->orderStatusService->markAsPaid($order);

        return $order;
    }
}
