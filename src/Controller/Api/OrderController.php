<?php

namespace App\Controller\Api;

use App\Dto\CreateOrderDto;
use App\Dto\CreateOrderItemDto;
use App\Enum\OrderChannel;
use App\Exception\OrderCreationException;
use App\Service\OrderApiPresenter;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderApiPresenter $orderApiPresenter,
    ) {
    }

    #[Route('', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'invalid_json', 'message' => 'Некорректный JSON.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dto = $this->buildDto($payload);
            $order = $this->orderService->create($dto);
        } catch (OrderCreationException $e) {
            return $this->json(
                ['error' => $e->getErrorCode(), 'message' => $e->getMessage()],
                $e->getStatusCode(),
            );
        }

        return $this->json(
            $this->orderApiPresenter->present($order, includePayment: true),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{uuid}', name: 'api_orders_show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $order = $this->orderService->getByUuid($uuid);
        if ($order === null) {
            return $this->json(
                ['error' => 'order_not_found', 'message' => 'Заказ не найден.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json($this->orderApiPresenter->present($order, includePayment: true));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildDto(array $payload): CreateOrderDto
    {
        $phone = trim((string) ($payload['phone'] ?? ''));
        $pickupDateRaw = trim((string) ($payload['pickup_date'] ?? ''));
        if ($pickupDateRaw === '') {
            throw new OrderCreationException('Укажите дату самовывоза.', 422, 'pickup_date_required');
        }

        $pickupDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $pickupDateRaw);
        if ($pickupDate === false) {
            throw new OrderCreationException('Некорректная дата самовывоза.', 422, 'pickup_date_invalid');
        }

        $itemsRaw = $payload['items'] ?? null;
        if (!\is_array($itemsRaw) || $itemsRaw === []) {
            throw new OrderCreationException('Добавьте хотя бы одно блюдо.', 422, 'items_required');
        }

        $items = [];
        foreach ($itemsRaw as $itemRaw) {
            if (!\is_array($itemRaw)) {
                throw new OrderCreationException('Некорректный формат позиции.', 422, 'invalid_item');
            }

            $items[] = new CreateOrderItemDto(
                (int) ($itemRaw['menu_day_dish_id'] ?? 0),
                (int) ($itemRaw['quantity'] ?? 1),
            );
        }

        $channel = OrderChannel::tryFrom((string) ($payload['channel'] ?? 'web')) ?? OrderChannel::Web;
        $pickupPointId = isset($payload['pickup_point_id']) ? (int) $payload['pickup_point_id'] : null;
        if ($pickupPointId === 0) {
            $pickupPointId = null;
        }

        return new CreateOrderDto(
            phone: $phone,
            pickupDate: $pickupDate,
            items: $items,
            name: trim((string) ($payload['name'] ?? '')),
            pickupPointId: $pickupPointId,
            channel: $channel,
            comment: isset($payload['comment']) ? (string) $payload['comment'] : null,
            personalDataConsent: filter_var($payload['personal_data_consent'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }
}
