<?php

namespace App\Service\Payment;

use App\Exception\PaymentConfirmationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class YooKassaPaymentProviderAdapter implements PaymentProviderAdapterInterface
{
    public function __construct(
        #[Autowire(param: 'app.yookassa_webhook_secret')]
        private readonly string $webhookSecret,
    ) {
    }

    public function getProvider(): string
    {
        return 'yookassa';
    }

    public function verify(Request $request): void
    {
        if ($this->webhookSecret === '') {
            throw new PaymentConfirmationException('YooKassa webhook не настроен.', Response::HTTP_SERVICE_UNAVAILABLE, 'webhook_not_configured');
        }

        $token = $request->headers->get('X-Yookassa-Secret', '');
        if (!hash_equals($this->webhookSecret, (string) $token)) {
            throw new PaymentConfirmationException('Неверный секрет YooKassa.', Response::HTTP_UNAUTHORIZED, 'unauthorized');
        }
    }

    public function parse(Request $request): ProviderPaymentEvent
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            throw new PaymentConfirmationException('Некорректный JSON.', Response::HTTP_BAD_REQUEST, 'invalid_json');
        }

        $event = (string) ($payload['event'] ?? '');
        if ($event !== 'payment.succeeded') {
            throw new PaymentConfirmationException('Событие не поддерживается.', Response::HTTP_UNPROCESSABLE_ENTITY, 'payment_not_confirmed');
        }

        $object = $payload['object'] ?? null;
        if (!\is_array($object)) {
            throw new PaymentConfirmationException('Некорректное тело webhook.', Response::HTTP_BAD_REQUEST, 'invalid_json');
        }

        $metadata = $object['metadata'] ?? [];
        if (!\is_array($metadata)) {
            $metadata = [];
        }

        $orderUuid = (string) ($metadata['order_uuid'] ?? '');
        if ($orderUuid === '') {
            throw new PaymentConfirmationException('metadata.order_uuid обязателен.', Response::HTTP_BAD_REQUEST, 'order_uuid_required');
        }

        $amountKopecks = null;
        $amount = $object['amount'] ?? null;
        if (\is_array($amount) && isset($amount['value'])) {
            $amountKopecks = (int) round(((float) $amount['value']) * 100);
        }

        $externalId = isset($object['id']) ? (string) $object['id'] : null;

        return new ProviderPaymentEvent($orderUuid, $amountKopecks, $externalId);
    }
}
