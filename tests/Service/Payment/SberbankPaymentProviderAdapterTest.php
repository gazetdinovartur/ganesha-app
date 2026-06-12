<?php

namespace App\Tests\Service\Payment;

use App\Service\Payment\SberbankPaymentProviderAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SberbankPaymentProviderAdapterTest extends TestCase
{
    public function testParsesPaidOrder(): void
    {
        $payload = [
            'orderNumber' => '018f3a2e-0000-7000-8000-000000000001',
            'orderStatus' => 2,
            'amount' => 35000,
            'orderId' => 'sber-123',
        ];

        $adapter = new SberbankPaymentProviderAdapter('secret');
        $event = $adapter->parse(new Request([], [], [], [], [], [], json_encode($payload, JSON_THROW_ON_ERROR)));

        self::assertSame('018f3a2e-0000-7000-8000-000000000001', $event->orderUuid);
        self::assertSame(35000, $event->amountKopecks);
        self::assertSame('sber-123', $event->externalId);
    }
}
