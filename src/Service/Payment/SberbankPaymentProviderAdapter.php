<?php

namespace App\Service\Payment;

use App\Exception\PaymentConfirmationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Адаптер webhook Сбербанка (эквайринг / СБП через API Сбера).
 *
 * При регистрации платежа в Сбере в orderNumber передаём order_uuid заказа.
 * Статус 2 (или DEPOSITED) — успешная оплата.
 */
final class SberbankPaymentProviderAdapter implements PaymentProviderAdapterInterface
{
    private const array PAID_STATUSES = [2, '2', 'DEPOSITED', 'PAID', 'SUCCESS'];

    public function __construct(
        #[Autowire(param: 'app.sber_webhook_secret')]
        private readonly string $webhookSecret,
    ) {
    }

    public function getProvider(): string
    {
        return 'sber';
    }

    public function verify(Request $request): void
    {
        if ($this->webhookSecret === '') {
            throw new PaymentConfirmationException('Sber webhook не настроен.', Response::HTTP_SERVICE_UNAVAILABLE, 'webhook_not_configured');
        }

        $token = $request->headers->get('X-Sber-Webhook-Secret', '');
        if (!hash_equals($this->webhookSecret, (string) $token)) {
            throw new PaymentConfirmationException('Неверный секрет Sber.', Response::HTTP_UNAUTHORIZED, 'unauthorized');
        }
    }

    public function parse(Request $request): ProviderPaymentEvent
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            throw new PaymentConfirmationException('Некорректный JSON.', Response::HTTP_BAD_REQUEST, 'invalid_json');
        }

        $status = $payload['orderStatus'] ?? $payload['status'] ?? null;
        if (!$this->isPaidStatus($status)) {
            throw new PaymentConfirmationException('Платёж не подтверждён.', Response::HTTP_UNPROCESSABLE_ENTITY, 'payment_not_confirmed');
        }

        $orderUuid = (string) ($payload['orderNumber'] ?? $payload['order_uuid'] ?? '');
        if ($orderUuid === '' && isset($payload['order']) && \is_array($payload['order'])) {
            $orderUuid = (string) ($payload['order']['orderNumber'] ?? $payload['order']['order_uuid'] ?? '');
        }

        if ($orderUuid === '') {
            throw new PaymentConfirmationException('orderNumber / order_uuid обязателен.', Response::HTTP_BAD_REQUEST, 'order_uuid_required');
        }

        $amount = null;
        if (isset($payload['amount'])) {
            $amount = (int) $payload['amount'];
        } elseif (isset($payload['order']) && \is_array($payload['order']) && isset($payload['order']['amount'])) {
            $amount = (int) $payload['order']['amount'];
        }

        $externalId = isset($payload['orderId']) ? (string) $payload['orderId'] : null;
        if ($externalId === null && isset($payload['mdOrder'])) {
            $externalId = (string) $payload['mdOrder'];
        }

        return new ProviderPaymentEvent($orderUuid, $amount, $externalId);
    }

    private function isPaidStatus(mixed $status): bool
    {
        if ($status === null) {
            return false;
        }

        return \in_array($status, self::PAID_STATUSES, true)
            || (\is_string($status) && strtoupper($status) === 'DEPOSITED');
    }
}
