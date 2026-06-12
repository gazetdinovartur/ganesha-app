<?php

namespace App\Tests\Service;

use App\Dto\CreateOrderItemDto;
use App\Dto\RepeatOrderDto;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\PickupPoint;
use App\Enum\OrderChannel;
use App\Exception\OrderCreationException;
use App\Repository\OrderRepository;
use App\Service\OrderRepeatService;
use App\Service\OrderService;
use PHPUnit\Framework\TestCase;

final class OrderRepeatServiceTest extends TestCase
{
    public function testBuildPreviewReturnsCustomerAndPickupPointOnly(): void
    {
        $customer = (new Customer())
            ->setPhone('+79123456789')
            ->setName('Анна');
        $pickupPoint = (new PickupPoint())
            ->setName('Хануман')
            ->setAddress('Щорса 37А')
            ->setPickupHours('12:00–14:00');

        $sourceOrder = $this->createMock(Order::class);
        $sourceOrder->method('getRepeatToken')->willReturn('repeat-token');
        $sourceOrder->method('getUuid')->willReturn(\Symfony\Component\Uid\Uuid::v4());
        $sourceOrder->method('getHumanNumber')->willReturn(42);
        $sourceOrder->method('getCustomer')->willReturn($customer);
        $sourceOrder->method('getPickupPoint')->willReturn($pickupPoint);

        $service = new OrderRepeatService(
            $this->createMock(OrderRepository::class),
            $this->createMock(OrderService::class),
        );

        $preview = $service->buildPreview($sourceOrder);

        self::assertSame('repeat-token', $preview['repeat_token']);
        self::assertSame(42, $preview['source_human_number']);
        self::assertSame('+79123456789', $preview['customer']['phone']);
        self::assertSame('Анна', $preview['customer']['name']);
        self::assertSame('Хануман', $preview['pickup_point']['name']);
        self::assertArrayNotHasKey('items', $preview);
    }

    public function testRepeatRequiresItems(): void
    {
        $customer = (new Customer())->setPhone('+79123456789')->setName('Анна');
        $pickupPoint = (new PickupPoint())->setName('Хануман');

        $sourceOrder = $this->createMock(Order::class);
        $sourceOrder->method('getCustomer')->willReturn($customer);
        $sourceOrder->method('getPickupPoint')->willReturn($pickupPoint);

        $repository = $this->createMock(OrderRepository::class);
        $repository->method('findOneByRepeatToken')->with('token')->willReturn($sourceOrder);

        $service = new OrderRepeatService($repository, $this->createMock(OrderService::class));

        $this->expectException(OrderCreationException::class);

        try {
            $service->repeat('token', new RepeatOrderDto(
                pickupDate: new \DateTimeImmutable('2026-06-15'),
                items: [],
            ));
        } catch (OrderCreationException $e) {
            self::assertSame('items_required', $e->getErrorCode());

            throw $e;
        }
    }

    public function testRepeatRejectsBotPlaceholderPhone(): void
    {
        $customer = (new Customer())->setPhone('bot:telegram:123')->setName('Гость');
        $sourceOrder = $this->createMock(Order::class);
        $sourceOrder->method('getCustomer')->willReturn($customer);

        $repository = $this->createMock(OrderRepository::class);
        $repository->method('findOneByRepeatToken')->willReturn($sourceOrder);

        $service = new OrderRepeatService($repository, $this->createMock(OrderService::class));

        $this->expectException(OrderCreationException::class);

        try {
            $service->repeat('token', new RepeatOrderDto(
                pickupDate: new \DateTimeImmutable('2026-06-15'),
                items: [new CreateOrderItemDto(1, 1)],
            ));
        } catch (OrderCreationException $e) {
            self::assertSame('customer_not_found', $e->getErrorCode());

            throw $e;
        }
    }

    public function testRepeatCreatesOrderWithSourceContactsAndPickupPoint(): void
    {
        $customer = (new Customer())->setPhone('+79123456789')->setName('Анна');
        $pickupPoint = $this->createMock(PickupPoint::class);
        $pickupPoint->method('getId')->willReturn(3);

        $sourceOrder = $this->createMock(Order::class);
        $sourceOrder->method('getCustomer')->willReturn($customer);
        $sourceOrder->method('getPickupPoint')->willReturn($pickupPoint);

        $repository = $this->createMock(OrderRepository::class);
        $repository->method('findOneByRepeatToken')->willReturn($sourceOrder);

        $createdOrder = $this->createMock(Order::class);

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function ($dto): bool {
                return $dto->phone === '+79123456789'
                    && $dto->name === 'Анна'
                    && $dto->pickupPointId === 3
                    && $dto->comment === 'без лука'
                    && $dto->channel === OrderChannel::Web
                    && $dto->items[0]->menuDayDishId === 7
                    && $dto->items[0]->quantity === 2;
            }))
            ->willReturn($createdOrder);

        $service = new OrderRepeatService($repository, $orderService);

        $order = $service->repeat('token', new RepeatOrderDto(
            pickupDate: new \DateTimeImmutable('2026-06-15'),
            items: [new CreateOrderItemDto(7, 2)],
            comment: 'без лука',
            channel: OrderChannel::Web,
        ));

        self::assertSame($createdOrder, $order);
    }
}
