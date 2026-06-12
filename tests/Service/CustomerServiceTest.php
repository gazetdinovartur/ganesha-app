<?php

namespace App\Tests\Service;

use App\Service\CustomerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerServiceTest extends TestCase
{
    #[DataProvider('phoneProvider')]
    public function testNormalizePhone(string $input, string $expected): void
    {
        self::assertSame($expected, CustomerService::normalizePhone($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function phoneProvider(): iterable
    {
        yield '10 digits' => ['9123456789', '+79123456789'];
        yield '11 digits with 8' => ['89123456789', '+79123456789'];
        yield 'formatted' => ['+7 (912) 345-67-89', '+79123456789'];
        yield 'empty' => ['   ', ''];
    }
}
