<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\KitchenSummaryService;
use App\Service\OrderStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class KitchenController extends AbstractController
{
    public function __construct(
        private readonly KitchenSummaryService $kitchenSummaryService,
        private readonly OrderStatusService $orderStatusService,
    ) {
    }

    #[Route('/admin/kitchen', name: 'admin_kitchen', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $dateParam = $request->query->get('date') ?? $request->request->get('date');
        $date = $dateParam
            ? new \DateTimeImmutable((string) $dateParam)
            : new \DateTimeImmutable('today');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('kitchen_batch', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Недействительный CSRF-токен.');
            }
            $this->handleBatch($request, $date);
        }

        $orders = $this->kitchenSummaryService->getOrdersForDate($date);
        $summaryAll = $this->kitchenSummaryService->getPortionSummary($date);
        $summaryPaid = $this->kitchenSummaryService->getPortionSummary($date, OrderStatus::Paid);

        return $this->render('admin/kitchen.html.twig', [
            'date' => $date,
            'orders' => $orders,
            'summaryAll' => $summaryAll,
            'summaryPaid' => $summaryPaid,
        ]);
    }

    private function handleBatch(Request $request, \DateTimeImmutable $date): void
    {
        $action = (string) $request->request->get('batch_action', '');
        $selectedIds = array_map('intval', (array) $request->request->all('order_ids'));

        $count = match ($action) {
            'confirm_payment' => $this->batchConfirmPayment($date, $selectedIds),
            'to_paid' => $this->orderStatusService->batchUpdateStatus($selectedIds, OrderStatus::Paid),
            'to_ready' => $this->orderStatusService->batchUpdateStatus($selectedIds, OrderStatus::Ready),
            'to_completed' => $this->orderStatusService->batchUpdateStatus($selectedIds, OrderStatus::Completed),
            'to_cancelled' => $this->orderStatusService->batchUpdateStatus($selectedIds, OrderStatus::Cancelled),
            'all_paid_to_ready' => $this->orderStatusService->batchUpdateStatusForDate($date, OrderStatus::Paid, OrderStatus::Ready),
            'all_ready_to_completed' => $this->orderStatusService->batchUpdateStatusForDate($date, OrderStatus::Ready, OrderStatus::Completed),
            default => 0,
        };

        if ($count > 0) {
            $this->addFlash('success', sprintf('Обновлено заказов: %d.', $count));
        } elseif ($action !== '') {
            $this->addFlash('warning', 'Ни один заказ не был обновлён.');
        }
    }

    /**
     * @param list<int> $orderIds
     */
    private function batchConfirmPayment(\DateTimeImmutable $date, array $orderIds): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $count = 0;
        foreach ($this->kitchenSummaryService->getOrdersForDate($date) as $order) {
            if (!in_array($order->getId(), $orderIds, true)) {
                continue;
            }
            if ($order->getStatus() !== OrderStatus::PendingPayment) {
                continue;
            }
            $this->orderStatusService->confirmPayment($order);
            ++$count;
        }

        return $count;
    }
}
