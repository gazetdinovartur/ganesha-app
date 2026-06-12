<?php

namespace App\Service;

use App\Dto\CreateOrderDto;
use App\Dto\CreateOrderItemDto;
use App\Dto\MappedRepeatItemDto;
use App\Dto\RepeatOrderDto;
use App\Entity\MenuDay;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Exception\OrderCreationException;
use App\Repository\MenuDayRepository;
use App\Repository\OrderRepository;

final class OrderRepeatService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MenuDayRepository $menuDayRepository,
        private readonly OrderService $orderService,
    ) {
    }

    public function getSourceOrder(string $repeatToken): ?Order
    {
        return $this->orderRepository->findOneByRepeatToken($repeatToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPreview(Order $sourceOrder, ?\DateTimeImmutable $pickupDate = null): array
    {
        $pickupDate ??= $sourceOrder->getPickupDate();
        $mappedItems = $this->mapItemsToMenu($sourceOrder, $pickupDate);

        $items = [];
        foreach ($mappedItems as $item) {
            $items[] = [
                'menu_day_dish_id' => $item->menuDayDishId,
                'quantity' => $item->quantity,
                'name' => $item->name,
                'unit_price' => $item->unitPrice,
            ];
        }

        $unavailable = [];
        if ($mappedItems === []) {
            foreach ($sourceOrder->getItems() as $orderItem) {
                $unavailable[] = (string) ($orderItem->getDishSnapshot()['name'] ?? 'Блюдо');
            }
        }

        return [
            'repeat_token' => $sourceOrder->getRepeatToken(),
            'source_order_uuid' => (string) $sourceOrder->getUuid(),
            'source_human_number' => $sourceOrder->getHumanNumber(),
            'pickup_date' => $pickupDate->format('Y-m-d'),
            'items' => $items,
            'unavailable' => $unavailable,
        ];
    }

    public function repeat(string $repeatToken, RepeatOrderDto $dto): Order
    {
        $sourceOrder = $this->getSourceOrder($repeatToken);
        if ($sourceOrder === null) {
            throw new OrderCreationException('Ссылка повтора недействительна.', 404, 'repeat_not_found');
        }

        $customer = $sourceOrder->getCustomer();
        if ($customer === null || $customer->getPhone() === '') {
            throw new OrderCreationException('Не удалось определить клиента.', 422, 'customer_not_found');
        }

        if (!$customer->hasPersonalDataConsent()) {
            throw new OrderCreationException(
                'Необходимо согласие на обработку персональных данных.',
                422,
                'consent_required',
            );
        }

        $mappedItems = $dto->items !== null
            ? array_map(
                static fn (CreateOrderItemDto $item) => new MappedRepeatItemDto(
                    $item->menuDayDishId,
                    $item->quantity,
                    '',
                    0,
                ),
                $dto->items,
            )
            : $this->mapItemsToMenu($sourceOrder, $dto->pickupDate);

        if ($mappedItems === []) {
            throw new OrderCreationException(
                'Не удалось повторить состав: блюда недоступны в меню на выбранный день.',
                422,
                'repeat_unavailable',
            );
        }

        $items = array_map(
            static fn (MappedRepeatItemDto $item) => new CreateOrderItemDto($item->menuDayDishId, $item->quantity),
            $mappedItems,
        );

        $createDto = new CreateOrderDto(
            phone: $customer->getPhone(),
            pickupDate: $dto->pickupDate,
            items: $items,
            name: $customer->getName(),
            pickupPointId: $sourceOrder->getPickupPoint()?->getId(),
            channel: $dto->channel,
            comment: $dto->comment ?? $sourceOrder->getComment(),
            personalDataConsent: true,
        );

        return $this->orderService->create($createDto);
    }

    /**
     * @return list<MappedRepeatItemDto>
     */
    private function mapItemsToMenu(Order $sourceOrder, \DateTimeImmutable $pickupDate): array
    {
        $menuDay = $this->menuDayRepository->findOneBy(['date' => $pickupDate->setTime(0, 0)]);
        if (!$menuDay instanceof MenuDay || !$menuDay->isPublished()) {
            return [];
        }

        $menuDayDishesByDishId = [];
        foreach ($menuDay->getDishes() as $menuDayDish) {
            $dishId = $menuDayDish->getDish()?->getId();
            if ($dishId === null || !$menuDayDish->isAvailable()) {
                continue;
            }

            $dish = $menuDayDish->getDish();
            if ($dish === null || !$dish->isActive()) {
                continue;
            }

            $menuDayDishesByDishId[$dishId] = $menuDayDish;
        }

        $items = [];
        foreach ($sourceOrder->getItems() as $orderItem) {
            $snapshot = $orderItem->getDishSnapshot();
            $dishId = (int) ($snapshot['dish_id'] ?? 0);
            if ($dishId <= 0 || !isset($menuDayDishesByDishId[$dishId])) {
                continue;
            }

            $menuDayDish = $menuDayDishesByDishId[$dishId];
            $items[] = new MappedRepeatItemDto(
                menuDayDishId: (int) $menuDayDish->getId(),
                quantity: $orderItem->getQuantity(),
                name: (string) ($snapshot['name'] ?? ''),
                unitPrice: $menuDayDish->getEffectivePrice(),
            );
        }

        return $items;
    }
}
