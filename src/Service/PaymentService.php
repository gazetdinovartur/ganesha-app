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
        private readonly NotificationService $notificationService,
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
        ?string $externalId = null,
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

        $paymentGroupUuid = $order->getPaymentGroupUuid();
        if ($paymentGroupUuid !== null) {
            return $this->confirmPaymentGroup($paymentGroupUuid, $amountKopecks, $externalId);
        }

        if ($amountKopecks !== null && $amountKopecks !== $order->getTotalAmount()) {
            throw new PaymentConfirmationException('Сумма платежа не совпадает с заказом.', 422, 'amount_mismatch');
        }

        $this->orderStatusService->markAsPaid($order);
        $this->notificationService->orderPaid($order, $externalId);

        return $order;
    }

    /**
     * @throws PaymentConfirmationException
     */
    private function confirmPaymentGroup(Uuid $paymentGroupUuid, ?int $amountKopecks, ?string $externalId): Order
    {
        $orders = $this->orderRepository->findByPaymentGroupUuid($paymentGroupUuid);
        if ($orders === []) {
            throw new PaymentConfirmationException('Группа заказов не найдена.', 404, 'order_group_not_found');
        }

        $pendingOrders = array_values(array_filter(
            $orders,
            static fn (Order $order): bool => $order->getStatus() !== OrderStatus::Cancelled,
        ));

        if ($pendingOrders === []) {
            throw new PaymentConfirmationException('Заказ отменён, оплату принять нельзя.', 409, 'order_cancelled');
        }

        $expectedTotal = 0;
        foreach ($pendingOrders as $groupOrder) {
            if ($groupOrder->getStatus() === OrderStatus::PendingPayment) {
                $expectedTotal += $groupOrder->getTotalAmount();
            }
        }

        if ($expectedTotal === 0) {
            return $pendingOrders[0];
        }

        $pendingToPay = array_values(array_filter(
            $pendingOrders,
            static fn (Order $order): bool => $order->getStatus() === OrderStatus::PendingPayment,
        ));

        if ($pendingToPay === []) {
            return $pendingOrders[0];
        }

        if ($amountKopecks !== null && $amountKopecks !== $expectedTotal) {
            throw new PaymentConfirmationException('Сумма платежа не совпадает с заказом.', 422, 'amount_mismatch');
        }

        $this->orderStatusService->markManyAsPaid($pendingToPay);

        foreach ($pendingToPay as $groupOrder) {
            $this->notificationService->orderPaid($groupOrder, $externalId);
        }

        return $pendingOrders[0];
    }
}
