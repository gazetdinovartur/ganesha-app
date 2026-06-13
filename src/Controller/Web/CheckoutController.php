<?php

namespace App\Controller\Web;

use App\Dto\CreateOrderDto;
use App\Dto\CreateOrderItemDto;
use App\Enum\OrderChannel;
use App\Exception\OrderCreationException;
use App\Form\Web\CheckoutFormType;
use App\Repository\PickupPointRepository;
use App\Service\OrderService;
use App\Service\PrivacyPolicyUrlProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
    private const SESSION_CART = 'web_checkout_cart';

    public function __construct(
        private readonly OrderService $orderService,
        private readonly PickupPointRepository $pickupPointRepository,
        private readonly PrivacyPolicyUrlProvider $privacyPolicyUrlProvider,
    ) {
    }

    #[Route('/order/checkout/prepare', name: 'web_checkout_prepare', methods: ['POST'])]
    public function prepare(Request $request, SessionInterface $session): Response
    {
        if (!$this->isCsrfTokenValid('checkout_prepare', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Сессия устарела. Добавьте блюда снова.');

            return $this->redirectToRoute('web_home');
        }

        $itemsRaw = json_decode((string) $request->request->get('items', '[]'), true);
        if (!\is_array($itemsRaw) || $itemsRaw === []) {
            $this->addFlash('error', 'Корзина пуста.');

            return $this->redirectToRoute('web_home');
        }

        try {
            $groups = $this->groupItemsByDate($itemsRaw);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('web_home');
        }

        $session->set(self::SESSION_CART, [
            'groups' => $groups,
        ]);

        return $this->redirectToRoute('web_checkout');
    }

    #[Route('/order/checkout', name: 'web_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request, SessionInterface $session): Response
    {
        $cart = $this->normalizeSessionCart($session->get(self::SESSION_CART));
        if ($cart === null) {
            $this->addFlash('error', 'Сначала выберите блюда в меню.');

            return $this->redirectToRoute('web_home');
        }

        $pickupPoints = $this->buildPickupPointChoices();
        if ($pickupPoints === []) {
            $this->addFlash('error', 'Нет доступных точек выдачи.');

            return $this->redirectToRoute('web_home');
        }

        $defaultPickupPointId = (string) array_key_first($pickupPoints);

        $form = $this->createForm(CheckoutFormType::class, [
            'cartGroupsJson' => json_encode($cart['groups'], JSON_THROW_ON_ERROR),
            'pickupPointId' => $defaultPickupPointId,
            'personalDataConsent' => false,
        ], [
            'pickup_points' => $pickupPoints,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            assert(\is_array($data));

            try {
                $orders = $this->orderService->createBatch($this->buildCreateOrderDtos($data));
            } catch (OrderCreationException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('web/checkout/index.html.twig', [
                    'form' => $form,
                    'cart' => $cart,
                    'privacy_policy_url' => $this->privacyPolicyUrlProvider->getUrl(),
                ]);
            }

            $session->remove(self::SESSION_CART);

            if (\count($orders) === 1) {
                return $this->redirectToRoute('web_order_pay', [
                    'uuid' => (string) $orders[0]->getUuid(),
                ]);
            }

            $paymentGroupUuid = $orders[0]->getPaymentGroupUuid();
            if ($paymentGroupUuid === null) {
                throw new \LogicException('Multi-order checkout requires payment group UUID.');
            }

            return $this->redirectToRoute('web_order_group_pay', [
                'paymentGroupUuid' => (string) $paymentGroupUuid,
            ]);
        }

        return $this->render('web/checkout/index.html.twig', [
            'form' => $form,
            'cart' => $cart,
            'privacy_policy_url' => $this->privacyPolicyUrlProvider->getUrl(),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $itemsRaw
     *
     * @return list<array{pickup_date: string, items: list<array<string, mixed>>}>
     */
    private function groupItemsByDate(array $itemsRaw): array
    {
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];

        foreach ($itemsRaw as $row) {
            if (!\is_array($row)) {
                throw new \InvalidArgumentException('Некорректная корзина.');
            }

            $date = trim((string) ($row['date'] ?? ''));
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new \InvalidArgumentException('У каждой позиции должен быть день самовывоза.');
            }

            if ((int) ($row['menu_day_dish_id'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('Некорректная позиция меню.');
            }

            if ((int) ($row['quantity'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('Количество должно быть больше нуля.');
            }

            $grouped[$date][] = $row;
        }

        if ($grouped === []) {
            throw new \InvalidArgumentException('Корзина пуста.');
        }

        ksort($grouped);

        $groups = [];
        foreach ($grouped as $date => $items) {
            $groups[] = [
                'pickup_date' => $date,
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * @return array{groups: list<array{pickup_date: string, items: list<array<string, mixed>>}>}|null
     */
    private function normalizeSessionCart(mixed $cart): ?array
    {
        if (!\is_array($cart)) {
            return null;
        }

        if (isset($cart['groups']) && \is_array($cart['groups']) && $cart['groups'] !== []) {
            return ['groups' => $cart['groups']];
        }

        if (isset($cart['pickup_date'], $cart['items']) && \is_array($cart['items']) && $cart['items'] !== []) {
            return [
                'groups' => [
                    [
                        'pickup_date' => (string) $cart['pickup_date'],
                        'items' => $cart['items'],
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function buildPickupPointChoices(): array
    {
        $choices = [];
        foreach ($this->pickupPointRepository->findAllActive() as $point) {
            $id = $point->getId();
            if ($id === null) {
                continue;
            }

            $choices[$point->getName().' · '.$point->getAddress()] = $id;
        }

        return $choices;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<CreateOrderDto>
     */
    private function buildCreateOrderDtos(array $data): array
    {
        $decoded = json_decode((string) $data['cartGroupsJson'], true);
        if (!\is_array($decoded) || $decoded === []) {
            throw new OrderCreationException('Корзина пуста.', 422, 'items_required');
        }

        $comment = isset($data['comment']) ? (string) $data['comment'] : null;
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        $dtos = [];
        foreach ($decoded as $group) {
            if (!\is_array($group)) {
                throw new OrderCreationException('Некорректная корзина.', 422, 'invalid_item');
            }

            $pickupDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($group['pickup_date'] ?? ''));
            if ($pickupDate === false) {
                throw new OrderCreationException('Некорректная дата самовывоза.', 422, 'pickup_date_invalid');
            }

            $items = $this->parseItemsJson(json_encode($group['items'] ?? [], JSON_THROW_ON_ERROR));

            $dtos[] = new CreateOrderDto(
                phone: (string) $data['phone'],
                pickupDate: $pickupDate,
                items: $items,
                name: (string) ($data['name'] ?? ''),
                pickupPointId: (int) $data['pickupPointId'],
                channel: OrderChannel::Web,
                comment: $comment,
                personalDataConsent: (bool) $data['personalDataConsent'],
            );
        }

        return $dtos;
    }

    /**
     * @return list<CreateOrderItemDto>
     */
    private function parseItemsJson(string $itemsJson): array
    {
        $decoded = json_decode($itemsJson, true);
        if (!\is_array($decoded) || $decoded === []) {
            throw new OrderCreationException('Корзина пуста.', 422, 'items_required');
        }

        $items = [];
        foreach ($decoded as $row) {
            if (!\is_array($row)) {
                throw new OrderCreationException('Некорректная корзина.', 422, 'invalid_item');
            }

            $items[] = new CreateOrderItemDto(
                (int) ($row['menu_day_dish_id'] ?? 0),
                max(1, (int) ($row['quantity'] ?? 1)),
            );
        }

        return $items;
    }
}
