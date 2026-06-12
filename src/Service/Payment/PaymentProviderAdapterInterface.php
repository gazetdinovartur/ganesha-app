<?php

namespace App\Service\Payment;

use Symfony\Component\HttpFoundation\Request;

interface PaymentProviderAdapterInterface
{
    public function getProvider(): string;

    public function verify(Request $request): void;

    public function parse(Request $request): ProviderPaymentEvent;
}
