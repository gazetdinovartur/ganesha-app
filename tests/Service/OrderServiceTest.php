<?php

namespace App\Tests\Service;

use App\Dto\CreateOrderDto;
use App\Dto\CreateOrderItemDto;
use App\Entity\Customer;
use App\Entity\Dish;
use App\Entity\MenuDay;
use App\Entity\MenuDayDish;
use App\Entity\PickupPoint;
use App\Enum\OrderChannel;
use App\Exception\OrderCreationException;
use App\Repository\MenuDayDishRepository;
use App\Repository\OrderRepository;
use App\Repository\PickupPointRepository;
use App\Service\CustomerService;
use App\Service\NotificationService;
use App\Service\OrderCutoffService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class OrderServiceTest extends TestCase
{
    public function testWebOrderRequiresConsent(): void
    {
        $service = $this->createServiceWithMenu();

        $this->expectException(OrderCreationException::class);

        try {
            $service->create(new CreateOrderDto(
                phone: '+79123456789',
                pickupDate: new \DateTimeImmutable('2026-06-14'),
                items: [new CreateOrderItemDto(1, 1)],
                channel: OrderChannel::Web,
                personalDataConsent: false,
            ));
        } catch (OrderCreationException $e) {
            self::assertSame('consent_required', $e->getErrorCode());

            throw $e;
        }
    }

    public function testTelegramOrderDoesNotRequireConsent(): void
    {
        $service = $this->createServiceWithMenu();

        $order = $service->create(new CreateOrderDto(
            phone: '+79123456789',
            pickupDate: new \DateTimeImmutable('2026-06-14'),
            items: [new CreateOrderItemDto(1, 1)],
            name: 'Анна',
            channel: OrderChannel::Telegram,
            personalDataConsent: false,
        ));

        self::assertSame(1, $order->getHumanNumber());
        self::assertSame(OrderChannel::Telegram, $order->getChannel());
    }

    private function createServiceWithMenu(): OrderService
    {
        $pickupPoint = (new PickupPoint())
            ->setName('Хануман')
            ->setAddress('Щорса 37А')
            ->setPickupHours('12:00–14:00')
            ->setIsActive(true);

        $pickupPointRepository = $this->createMock(PickupPointRepository::class);
        $pickupPointRepository->method('findFirstActive')->willReturn($pickupPoint);

        $menuDay = $this->createMock(MenuDay::class);
        $menuDay->method('getDate')->willReturn(new \DateTimeImmutable('2026-06-14'));
        $menuDay->method('isPublished')->willReturn(true);

        $dish = $this->createMock(Dish::class);
        $dish->method('isActive')->willReturn(true);
        $dish->method('getName')->willReturn('Дал');
        $dish->method('getId')->willReturn(1);

        $menuDayDish = $this->createMock(MenuDayDish::class);
        $menuDayDish->method('getMenuDay')->willReturn($menuDay);
        $menuDayDish->method('isAvailable')->willReturn(true);
        $menuDayDish->method('getDish')->willReturn($dish);
        $menuDayDish->method('getEffectivePrice')->willReturn(35000);
        $menuDayDish->method('incrementOrderedPortions');

        $menuDayDishRepository = $this->createMock(MenuDayDishRepository::class);
        $menuDayDishRepository->method('findOneForOrdering')->with(1)->willReturn($menuDayDish);

        $customer = (new Customer())->setPhone('+79123456789')->setName('Анна');
        $customerService = $this->createMock(CustomerService::class);
        $customerService->method('findOrCreate')->willReturn($customer);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('getNextHumanNumber')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback) => $callback(),
        );

        return new OrderService(
            $orderRepository,
            $menuDayDishRepository,
            $pickupPointRepository,
            $customerService,
            new OrderCutoffService('Asia/Yekaterinburg', 18, 7),
            $entityManager,
            $this->createMock(NotificationService::class),
        );
    }
}
