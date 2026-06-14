<?php

namespace App\Controller\Admin;

use App\Enum\OrderStatus;
use App\Service\KitchenSummaryService;
use App\Service\OrderStatusService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/kitchen', name: 'kitchen', options: ['methods' => ['GET', 'POST']])]
final class KitchenController extends AbstractController
{
    public function __construct(
        private readonly KitchenSummaryService $kitchenSummaryService,
        private readonly OrderStatusService $orderStatusService,
        #[Autowire(param: 'app.timezone')]
        private readonly string $timezone,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $timezone = new \DateTimeZone($this->timezone);
        $today = new \DateTimeImmutable('today', $timezone);
        $tomorrow = $today->modify('+1 day');
        $weekStart = $today->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days');

        $range = (string) ($request->query->get('range') ?? $request->request->get('range', ''));
        $isWeekView = $range === 'week';

        $dateParam = $request->query->get('date') ?? $request->request->get('date');
        $date = $isWeekView
            ? $weekStart
            : ($dateParam
                ? new \DateTimeImmutable((string) $dateParam, $timezone)
                : $today);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('kitchen_batch', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Недействительный CSRF-токен.');
            }
            $this->handleBatch($request, $date);
        }

        if ($isWeekView) {
            $orders = $this->kitchenSummaryService->getOrdersForDateRange($weekStart, $weekEnd);
        } else {
            $orders = $this->kitchenSummaryService->getOrdersForDate($date);
        }

        $summaryAll = $this->kitchenSummaryService->buildPortionSummary($orders);
        $summaryPaid = $this->kitchenSummaryService->buildPortionSummary($orders, OrderStatus::Paid);

        return $this->render('admin/kitchen.html.twig', [
            'viewMode' => $isWeekView ? 'week' : 'day',
            'date' => $date,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'tomorrow' => $tomorrow,
            'today' => $today,
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
}
