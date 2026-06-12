<?php

namespace App\Tests\Service;

use App\Exception\OrderCreationException;
use App\Service\OrderCutoffService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderCutoffServiceTest extends TestCase
{
    private OrderCutoffService $service;

    protected function setUp(): void
    {
        $this->service = new OrderCutoffService('Asia/Yekaterinburg', 18, 7);
    }

    public function testAllowsOrderBeforeCutoff(): void
    {
        $pickupDate = new \DateTimeImmutable('2026-06-14', new \DateTimeZone('Asia/Yekaterinburg'));
        $now = new \DateTimeImmutable('2026-06-13 17:59:59', new \DateTimeZone('Asia/Yekaterinburg'));

        $this->service->assertCanOrderForDate($pickupDate, $now);

        $this->addToAssertionCount(1);
    }

    public function testRejectsOrderAfterCutoff(): void
    {
        $pickupDate = new \DateTimeImmutable('2026-06-14', new \DateTimeZone('Asia/Yekaterinburg'));
        $now = new \DateTimeImmutable('2026-06-13 18:00:00', new \DateTimeZone('Asia/Yekaterinburg'));

        $this->expectException(OrderCreationException::class);

        try {
            $this->service->assertCanOrderForDate($pickupDate, $now);
        } catch (OrderCreationException $e) {
            self::assertSame('cutoff_passed', $e->getErrorCode());

            throw $e;
        }
    }

    #[DataProvider('menuHorizonProvider')]
    public function testMenuHorizon(string $pickupDate, bool $expected): void
    {
        $today = new \DateTimeImmutable('2026-06-13', new \DateTimeZone('Asia/Yekaterinburg'));
        $date = new \DateTimeImmutable($pickupDate, new \DateTimeZone('Asia/Yekaterinburg'));

        self::assertSame($expected, $this->service->isWithinMenuHorizon($date, $today));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function menuHorizonProvider(): iterable
    {
        yield 'today' => ['2026-06-13', true];
        yield 'last day' => ['2026-06-19', true];
        yield 'too far' => ['2026-06-20', false];
        yield 'past' => ['2026-06-12', false];
    }
}
