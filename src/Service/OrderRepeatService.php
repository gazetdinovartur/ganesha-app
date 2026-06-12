<?php

namespace App\Service;

use App\Dto\CreateOrderDto;
use App\Dto\CreateOrderItemDto;
use App\Dto\RepeatOrderDto;
use App\Entity\Order;
use App\Exception\OrderCreationException;
use App\Repository\OrderRepository;

class OrderRepeatService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderService $orderService,
    ) {
    }

    public function getSourceOrder(string $repeatToken): ?Order
    {
        return $this->orderRepository->findOneByRepeatToken($repeatToken);
    }

    /**
     * Префill для формы: телефон, имя, точка выдачи. Состав и дату клиент выбирает сам.
     *
     * @return array<string, mixed>
     */
    public function buildPreview(Order $sourceOrder): array
    {
        $customer = $sourceOrder->getCustomer();
        $pickupPoint = $sourceOrder->getPickupPoint();

        return [
            'repeat_token' => $sourceOrder->getRepeatToken(),
            'source_order_uuid' => (string) $sourceOrder->getUuid(),
            'source_human_number' => $sourceOrder->getHumanNumber(),
            'customer' => [
                'phone' => $customer?->getPhone(),
                'name' => $customer?->getName(),
            ],
            'pickup_point' => [
                'id' => $pickupPoint?->getId(),
                'name' => $pickupPoint?->getName(),
                'address' => $pickupPoint?->getAddress(),
                'pickup_hours' => $pickupPoint?->getPickupHours(),
            ],
        ];
    }

    public function repeat(string $repeatToken, RepeatOrderDto $dto): Order
    {
        $sourceOrder = $this->getSourceOrder($repeatToken);
        if ($sourceOrder === null) {
            throw new OrderCreationException('Ссылка повтора недействительна.', 404, 'repeat_not_found');
        }

        $customer = $sourceOrder->getCustomer();
        if ($customer === null || $customer->getPhone() === '' || str_starts_with($customer->getPhone(), 'bot:')) {
            throw new OrderCreationException('Не удалось определить телефон клиента.', 422, 'customer_not_found');
        }

        if ($dto->items === []) {
            throw new OrderCreationException('Добавьте хотя бы одно блюдо.', 422, 'items_required');
        }

        $pickupPointId = $sourceOrder->getPickupPoint()?->getId();
        if ($pickupPointId === null) {
            throw new OrderCreationException('Точка выдачи недоступна.', 422, 'pickup_point_not_found');
        }

        $createDto = new CreateOrderDto(
            phone: $customer->getPhone(),
            pickupDate: $dto->pickupDate,
            items: $dto->items,
            name: $customer->getName(),
            pickupPointId: $pickupPointId,
            channel: $dto->channel,
            comment: $dto->comment !== null && trim($dto->comment) !== '' ? trim($dto->comment) : null,
            personalDataConsent: true,
        );

        return $this->orderService->create($createDto);
    }
}
