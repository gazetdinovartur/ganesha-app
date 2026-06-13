<?php

namespace App\Service;

use App\Dto\CreateOrderDto;
use App\Dto\CreateOrderItemDto;
use App\Entity\MenuDayDish;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\PickupPoint;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Exception\OrderCreationException;
use App\Repository\MenuDayDishRepository;
use App\Repository\OrderRepository;
use App\Repository\PickupPointRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MenuDayDishRepository $menuDayDishRepository,
        private readonly PickupPointRepository $pickupPointRepository,
        private readonly CustomerService $customerService,
        private readonly OrderCutoffService $orderCutoffService,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function create(CreateOrderDto $dto): Order
    {
        $orders = $this->createBatch([$dto]);

        return $orders[0];
    }

    /**
     * @param list<CreateOrderDto> $dtos
     *
     * @return list<Order>
     */
    public function createBatch(array $dtos): array
    {
        if ($dtos === []) {
            throw new OrderCreationException('Добавьте хотя бы одно блюдо.', 422, 'items_required');
        }

        $paymentGroupUuid = \count($dtos) > 1 ? Uuid::v7() : null;

        return $this->entityManager->wrapInTransaction(function () use ($dtos, $paymentGroupUuid): array {
            $orders = [];
            foreach ($dtos as $dto) {
                $orders[] = $this->createOrder($dto, $paymentGroupUuid);
            }

            return $orders;
        });
    }

    private function createOrder(CreateOrderDto $dto, ?Uuid $paymentGroupUuid): Order
    {
        if ($dto->items === []) {
            throw new OrderCreationException('Добавьте хотя бы одно блюдо.', 422, 'items_required');
        }

        $pickupDate = $dto->pickupDate->setTime(0, 0);
        $this->orderCutoffService->assertCanOrderForDate($pickupDate);

        $pickupPoint = $this->resolvePickupPoint($dto->pickupPointId);
        $mergedItems = $this->mergeItems($dto->items);
        $menuDayDishes = $this->resolveMenuDayDishes($mergedItems, $pickupDate);

        if ($dto->channel === OrderChannel::Web && !$dto->personalDataConsent) {
            throw new OrderCreationException(
                'Необходимо согласие на обработку персональных данных.',
                422,
                'consent_required',
            );
        }

        // Согласие для web проверяется выше; grantConsent — ниже. requireConsent=false,
        // иначе новый клиент с галочкой не создаётся.
        $customer = $this->customerService->findOrCreate(
            $dto->phone,
            $dto->name,
            requireConsent: false,
        );

        if ($dto->channel === OrderChannel::Web && $dto->personalDataConsent && !$customer->hasPersonalDataConsent()) {
            $this->customerService->grantConsent($customer);
        }

        $order = (new Order())
            ->setHumanNumber($this->orderRepository->getNextHumanNumber())
            ->setCustomer($customer)
            ->setPickupDate($pickupDate)
            ->setPickupPoint($pickupPoint)
            ->setChannel($dto->channel)
            ->setStatus(OrderStatus::PendingPayment)
            ->setComment($dto->comment !== null && trim($dto->comment) !== '' ? trim($dto->comment) : null)
            ->setPaymentGroupUuid($paymentGroupUuid);

        foreach ($mergedItems as $itemDto) {
            $menuDayDish = $menuDayDishes[$itemDto->menuDayDishId];
            $dish = $menuDayDish->getDish();
            if ($dish === null) {
                throw new OrderCreationException('Блюдо не найдено.', 422, 'dish_not_found');
            }

            $orderItem = (new OrderItem())
                ->setDish($dish)
                ->setQuantity($itemDto->quantity)
                ->setDishSnapshot([
                    'dish_id' => $dish->getId(),
                    'name' => $dish->getName(),
                    'unit_price' => $menuDayDish->getEffectivePrice(),
                ]);

            $order->addItem($orderItem);
            $menuDayDish->incrementOrderedPortions($itemDto->quantity);
        }

        $order->recalculateTotal();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->notificationService->newOrderForAdmin($order);

        return $order;
    }

    public function getByUuid(string $uuid): ?Order
    {
        return $this->orderRepository->findOneByUuid($uuid);
    }

    private function resolvePickupPoint(?int $pickupPointId): PickupPoint
    {
        if ($pickupPointId !== null) {
            $pickupPoint = $this->pickupPointRepository->findActiveById($pickupPointId);
            if ($pickupPoint === null) {
                throw new OrderCreationException('Точка выдачи недоступна.', 422, 'pickup_point_not_found');
            }

            return $pickupPoint;
        }

        $pickupPoint = $this->pickupPointRepository->findFirstActive();
        if ($pickupPoint === null) {
            throw new OrderCreationException('Нет доступных точек выдачи.', 503, 'pickup_point_unavailable');
        }

        return $pickupPoint;
    }

    /**
     * @param list<CreateOrderItemDto> $items
     *
     * @return list<CreateOrderItemDto>
     */
    private function mergeItems(array $items): array
    {
        /** @var array<int, CreateOrderItemDto> $merged */
        $merged = [];

        foreach ($items as $item) {
            if ($item->menuDayDishId <= 0) {
                throw new OrderCreationException('Некорректная позиция меню.', 422, 'invalid_item');
            }

            if ($item->quantity <= 0) {
                throw new OrderCreationException('Количество должно быть больше нуля.', 422, 'invalid_quantity');
            }

            if (isset($merged[$item->menuDayDishId])) {
                $existing = $merged[$item->menuDayDishId];
                $merged[$item->menuDayDishId] = new CreateOrderItemDto(
                    $existing->menuDayDishId,
                    $existing->quantity + $item->quantity,
                );

                continue;
            }

            $merged[$item->menuDayDishId] = $item;
        }

        return array_values($merged);
    }

    /**
     * @param list<CreateOrderItemDto> $items
     *
     * @return array<int, MenuDayDish>
     */
    private function resolveMenuDayDishes(array $items, \DateTimeImmutable $pickupDate): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $menuDayDish = $this->menuDayDishRepository->findOneForOrdering($item->menuDayDishId);
            if ($menuDayDish === null) {
                throw new OrderCreationException('Позиция меню не найдена.', 422, 'menu_item_not_found');
            }

            $menuDay = $menuDayDish->getMenuDay();
            if ($menuDay === null || $menuDay->getDate()->format('Y-m-d') !== $pickupDate->format('Y-m-d')) {
                throw new OrderCreationException('Блюдо не относится к выбранному дню.', 422, 'menu_day_mismatch');
            }

            if (!$menuDay->isPublished()) {
                throw new OrderCreationException('Меню на этот день ещё не опубликовано.', 422, 'menu_not_published');
            }

            if (!$menuDayDish->isAvailable()) {
                throw new OrderCreationException(
                    sprintf('«%s» временно недоступно.', $menuDayDish->getDish()?->getName() ?? 'Блюдо'),
                    422,
                    'dish_not_available',
                );
            }

            $dish = $menuDayDish->getDish();
            if ($dish === null || !$dish->isActive()) {
                throw new OrderCreationException('Блюдо недоступно для заказа.', 422, 'dish_inactive');
            }

            $resolved[$item->menuDayDishId] = $menuDayDish;
        }

        return $resolved;
    }
}
