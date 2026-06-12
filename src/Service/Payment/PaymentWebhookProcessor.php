<?php

namespace App\Service\Payment;

use App\Entity\Order;
use App\Exception\PaymentConfirmationException;
use App\Service\PaymentService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

final class PaymentWebhookProcessor
{
    /** @var array<string, PaymentProviderAdapterInterface> */
    private array $adaptersByProvider = [];

    /**
     * @param iterable<PaymentProviderAdapterInterface> $adapters
     */
    public function __construct(
        #[AutowireIterator('app.payment_provider_adapter')]
        iterable $adapters,
        private readonly PaymentService $paymentService,
    ) {
        foreach ($adapters as $adapter) {
            $this->adaptersByProvider[$adapter->getProvider()] = $adapter;
        }
    }

    public function process(string $provider, Request $request): Order
    {
        $adapter = $this->adaptersByProvider[$provider] ?? null;
        if ($adapter === null) {
            throw new PaymentConfirmationException(
                sprintf('Провайдер «%s» не поддерживается.', $provider),
                404,
                'provider_not_found',
            );
        }

        $adapter->verify($request);
        $event = $adapter->parse($request);

        return $this->paymentService->confirmPayment($event->orderUuid, $event->amountKopecks, $event->externalId);
    }
}
