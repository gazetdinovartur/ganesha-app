<?php

namespace App\Controller\Web;

use App\Repository\OrderRepository;
use App\Service\OrderApiPresenter;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class OrderPageController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderRepository $orderRepository,
        private readonly OrderApiPresenter $orderApiPresenter,
    ) {
    }

    #[Route('/order/group/{paymentGroupUuid}/pay', name: 'web_order_group_pay', methods: ['GET'])]
    public function groupPay(string $paymentGroupUuid): Response
    {
        $orders = $this->loadGroupOrders($paymentGroupUuid);
        if ($orders === null) {
            throw $this->createNotFoundException('Заказ не найден.');
        }

        $pendingOrders = array_values(array_filter(
            $orders,
            static fn ($order): bool => $order->getStatus()->value === 'pending_payment',
        ));

        if ($pendingOrders === []) {
            return $this->redirectToRoute('web_order_group_status', [
                'paymentGroupUuid' => $paymentGroupUuid,
            ]);
        }

        $totalAmount = 0;
        foreach ($pendingOrders as $order) {
            $totalAmount += $order->getTotalAmount();
        }

        return $this->render('web/order/group_pay.html.twig', [
            'orders' => $pendingOrders,
            'payment_group_uuid' => $paymentGroupUuid,
            'total_amount' => $totalAmount,
            'payment' => $this->orderApiPresenter->paymentBlock($paymentGroupUuid),
            'poll_url' => $this->generateUrl('api_orders_show', ['uuid' => (string) $pendingOrders[0]->getUuid()]),
        ]);
    }

    #[Route('/order/group/{paymentGroupUuid}', name: 'web_order_group_status', methods: ['GET'])]
    public function groupStatus(string $paymentGroupUuid): Response
    {
        $orders = $this->loadGroupOrders($paymentGroupUuid);
        if ($orders === null) {
            throw $this->createNotFoundException('Заказ не найден.');
        }

        $totalAmount = 0;
        $pendingCount = 0;
        foreach ($orders as $order) {
            $totalAmount += $order->getTotalAmount();
            if ($order->getStatus()->value === 'pending_payment') {
                ++$pendingCount;
            }
        }

        return $this->render('web/order/group_status.html.twig', [
            'orders' => $orders,
            'payment_group_uuid' => $paymentGroupUuid,
            'total_amount' => $totalAmount,
            'pending_count' => $pendingCount,
        ]);
    }

    #[Route('/order/{uuid}/pay', name: 'web_order_pay', methods: ['GET'])]
    public function pay(string $uuid): Response
    {
        $order = $this->orderService->getByUuid($uuid);
        if ($order === null) {
            throw $this->createNotFoundException('Заказ не найден.');
        }

        $paymentGroupUuid = $order->getPaymentGroupUuid();
        if ($paymentGroupUuid !== null) {
            return $this->redirectToRoute('web_order_group_pay', [
                'paymentGroupUuid' => (string) $paymentGroupUuid,
            ]);
        }

        if ($order->getStatus()->value !== 'pending_payment') {
            return $this->redirectToRoute('web_order_status', ['uuid' => $uuid]);
        }

        return $this->render('web/order/pay.html.twig', [
            'order' => $order,
            'presented' => $this->orderApiPresenter->present($order, includePayment: true),
        ]);
    }

    #[Route('/order/{uuid}', name: 'web_order_status', methods: ['GET'])]
    public function status(string $uuid): Response
    {
        $order = $this->orderService->getByUuid($uuid);
        if ($order === null) {
            throw $this->createNotFoundException('Заказ не найден.');
        }

        $groupOrders = [];
        $paymentGroupUuid = $order->getPaymentGroupUuid();
        if ($paymentGroupUuid !== null) {
            $groupOrders = $this->orderRepository->findByPaymentGroupUuid($paymentGroupUuid);
        }

        return $this->render('web/order/status.html.twig', [
            'order' => $order,
            'presented' => $this->orderApiPresenter->present($order, includePayment: false),
            'poll_url' => $this->generateUrl('api_orders_show', ['uuid' => $uuid]),
            'payment_group_uuid' => $paymentGroupUuid !== null ? (string) $paymentGroupUuid : null,
            'group_orders' => $groupOrders,
        ]);
    }

    /**
     * @return list<\App\Entity\Order>|null
     */
    private function loadGroupOrders(string $paymentGroupUuid): ?array
    {
        if (!Uuid::isValid($paymentGroupUuid)) {
            return null;
        }

        $orders = $this->orderRepository->findByPaymentGroupUuid(Uuid::fromString($paymentGroupUuid));

        return $orders === [] ? null : $orders;
    }
}
