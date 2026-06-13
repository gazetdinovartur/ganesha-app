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
        #[Autowire(param: 'app.public_base_url')]
        private readonly string $publicBaseUrl,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $debug,
    ) {
    }

    /**
     * @return array{qr_url: ?string, card: ?string, comment_hint: ?string}
     */
    public function paymentBlock(?string $commentHint = null): array
    {
        return [
            'qr_url' => $this->resolveQrUrl(),
            'card' => $this->resolveCard(),
            'comment_hint' => $commentHint,
        ];
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
            'repeat_token' => $order->getRepeatToken(),
            'repeat_url' => $this->repeatUrl($order->getRepeatToken()),
            'items' => $items,
        ];

        if ($includePayment && $order->getStatus()->value === 'pending_payment') {
            $data['payment'] = $this->paymentBlock((string) $order->getUuid());
        }

        return $data;
    }

    private function resolveQrUrl(): ?string
    {
        if ($this->paymentQrUrl !== '') {
            return $this->paymentQrUrl;
        }

        if ($this->debug) {
            return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=ganesha-dev-pay';
        }

        return null;
    }

    private function resolveCard(): ?string
    {
        if ($this->paymentCard !== '') {
            return $this->paymentCard;
        }

        if ($this->debug) {
            return '2200 1234 5678 9012 · Ganesha (демо)';
        }

        return null;
    }

    private function repeatUrl(string $repeatToken): string
    {
        if ($this->publicBaseUrl === '') {
            return '/order/repeat/'.$repeatToken;
        }

        return rtrim($this->publicBaseUrl, '/').'/order/repeat/'.$repeatToken;
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
