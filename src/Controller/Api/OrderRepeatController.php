<?php

namespace App\Controller\Api;

use App\Dto\CreateOrderItemDto;
use App\Dto\RepeatOrderDto;
use App\Enum\OrderChannel;
use App\Exception\OrderCreationException;
use App\Service\OrderApiPresenter;
use App\Service\OrderRepeatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders/repeat')]
final class OrderRepeatController extends AbstractController
{
    public function __construct(
        private readonly OrderRepeatService $orderRepeatService,
        private readonly OrderApiPresenter $orderApiPresenter,
    ) {
    }

    #[Route('/{token}', name: 'api_orders_repeat_preview', methods: ['GET'])]
    public function preview(string $token, Request $request): JsonResponse
    {
        $order = $this->orderRepeatService->getSourceOrder($token);
        if ($order === null) {
            return $this->json(['error' => 'repeat_not_found', 'message' => 'Ссылка недействительна.'], Response::HTTP_NOT_FOUND);
        }

        $pickupDateRaw = $request->query->get('pickup_date');
        $pickupDate = null;
        if (\is_string($pickupDateRaw) && $pickupDateRaw !== '') {
            $pickupDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $pickupDateRaw) ?: null;
        }

        return $this->json($this->orderRepeatService->buildPreview($order, $pickupDate));
    }

    #[Route('/{token}', name: 'api_orders_repeat_create', methods: ['POST'])]
    public function create(string $token, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'invalid_json', 'message' => 'Некорректный JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $pickupDateRaw = trim((string) ($payload['pickup_date'] ?? ''));
        if ($pickupDateRaw === '') {
            return $this->json(['error' => 'pickup_date_required', 'message' => 'Укажите дату.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pickupDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $pickupDateRaw);
        if ($pickupDate === false) {
            return $this->json(['error' => 'pickup_date_invalid', 'message' => 'Некорректная дата.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $items = null;
        if (isset($payload['items']) && \is_array($payload['items'])) {
            $items = [];
            foreach ($payload['items'] as $itemRaw) {
                if (!\is_array($itemRaw)) {
                    continue;
                }
                $items[] = new CreateOrderItemDto(
                    (int) ($itemRaw['menu_day_dish_id'] ?? 0),
                    (int) ($itemRaw['quantity'] ?? 1),
                );
            }
        }

        $channel = OrderChannel::tryFrom((string) ($payload['channel'] ?? 'web')) ?? OrderChannel::Web;

        try {
            $order = $this->orderRepeatService->repeat($token, new RepeatOrderDto(
                pickupDate: $pickupDate,
                items: $items,
                comment: isset($payload['comment']) ? (string) $payload['comment'] : null,
                channel: $channel,
            ));
        } catch (OrderCreationException $e) {
            return $this->json(['error' => $e->getErrorCode(), 'message' => $e->getMessage()], $e->getStatusCode());
        }

        return $this->json($this->orderApiPresenter->present($order, includePayment: true), Response::HTTP_CREATED);
    }
}
