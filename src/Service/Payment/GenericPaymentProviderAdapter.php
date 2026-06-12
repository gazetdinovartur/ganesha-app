<?php

namespace App\Service\Payment;

use App\Exception\PaymentConfirmationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GenericPaymentProviderAdapter implements PaymentProviderAdapterInterface
{
    public function __construct(
        #[Autowire(param: 'app.payment_webhook_secret')]
        private readonly string $webhookSecret,
    ) {
    }

    public function getProvider(): string
    {
        return 'generic';
    }

    public function verify(Request $request): void
    {
        if ($this->webhookSecret === '' || $this->webhookSecret === 'change_me') {
            throw new PaymentConfirmationException('Webhook не настроен.', Response::HTTP_SERVICE_UNAVAILABLE, 'webhook_not_configured');
        }

        $token = $request->headers->get('X-Payment-Token', '');
        if (!hash_equals($this->webhookSecret, (string) $token)) {
            throw new PaymentConfirmationException('Неверный токен.', Response::HTTP_UNAUTHORIZED, 'unauthorized');
        }
    }

    public function parse(Request $request): ProviderPaymentEvent
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            throw new PaymentConfirmationException('Некорректный JSON.', Response::HTTP_BAD_REQUEST, 'invalid_json');
        }

        $orderUuid = isset($payload['order_uuid']) ? (string) $payload['order_uuid'] : '';
        if ($orderUuid === '') {
            throw new PaymentConfirmationException('order_uuid обязателен.', Response::HTTP_BAD_REQUEST, 'order_uuid_required');
        }

        $amount = isset($payload['amount']) ? (int) $payload['amount'] : null;
        $externalId = isset($payload['external_id']) ? (string) $payload['external_id'] : null;

        return new ProviderPaymentEvent($orderUuid, $amount, $externalId);
    }
}
