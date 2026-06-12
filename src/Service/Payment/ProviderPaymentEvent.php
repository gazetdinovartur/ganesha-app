<?php

namespace App\Service\Payment;

final readonly class ProviderPaymentEvent
{
    public function __construct(
        public string $orderUuid,
        public ?int $amountKopecks = null,
        public ?string $externalId = null,
    ) {
    }
}
