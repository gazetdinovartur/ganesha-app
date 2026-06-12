<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderApiPresenter
{
    public function __construct(
        #[Autowire(param: 'app.payment_qr_url')]
        private readonly string $paymentQrUrl,
        #[Autowire(param: 'app.payment_card')]
        private readonly string $paymentCard,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order, bool $includePayment = false): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $snapshot = $item->getDishSnapshot();
            $items[] = [
                'name' => $snapshot['name'] ?? '',
                'quantity' => $item->getQuantity(),
                'unit_price' => $snapshot['unit_price'] ?? 0,
                'line_total' => $item->getLineTotal(),
            ];
        }

        $data = [
            'order_uuid' => (string) $order->getUuid(),
            'human_number' => $order->getHumanNumber(),
            'status' => $order->getStatus()->value,
            'status_label' => $order->getStatus()->label(),
            'pickup_date' => $order->getPickupDate()->format('Y-m-d'),
            'pickup_point' => [
                'id' => $order->getPickupPoint()?->getId(),
                'name' => $order->getPickupPoint()?->getName(),
                'address' => $order->getPickupPoint()?->getAddress(),
                'pickup_hours' => $order->getPickupPoint()?->getPickupHours(),
            ],
            'channel' => $order->getChannel()->value,
            'total_amount' => $order->getTotalAmount(),
            'comment' => $order->getComment(),
            'created_at' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'paid_at' => $order->getPaidAt()?->format(\DateTimeInterface::ATOM),
            'items' => $items,
        ];

        if ($includePayment && $order->getStatus()->value === 'pending_payment') {
            $data['payment'] = [
                'qr_url' => $this->paymentQrUrl !== '' ? $this->paymentQrUrl : null,
                'card' => $this->paymentCard !== '' ? $this->paymentCard : null,
                'comment_hint' => (string) $order->getUuid(),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPaymentConfirmation(Order $order): array
    {
        return [
            'status' => $order->getStatus()->value,
            'order_uuid' => (string) $order->getUuid(),
            'human_number' => $order->getHumanNumber(),
            'paid_at' => $order->getPaidAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
