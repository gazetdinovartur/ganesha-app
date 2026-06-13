<?php

namespace App\Service;

use App\Exception\OrderCreationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WebMenuService
{
    public function __construct(
        private readonly MenuCatalogService $menuCatalogService,
        private readonly OrderCutoffService $orderCutoffService,
        #[Autowire(param: 'app.timezone')]
        private readonly string $timezone,
        #[Autowire(param: 'app.order_cutoff_hour')]
        private readonly int $cutoffHour,
    ) {
    }

    /**
     * @return array{
     *     days: list<array<string, mixed>>,
     *     cutoff_hour: int,
     *     timezone: string,
     *     today: string
     * }
     */
    public function buildMenuPage(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
        $today = $now->setTime(0, 0);
        $days = [];

        foreach ($this->menuCatalogService->getPublishedMenu() as $day) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day['date'], new \DateTimeZone($this->timezone));
            if ($date === false) {
                continue;
            }

            $orderable = true;
            $orderBlockReason = null;

            try {
                $this->orderCutoffService->assertCanOrderForDate($date, $now);
            } catch (OrderCreationException $e) {
                $orderable = false;
                $orderBlockReason = $e->getMessage();
            }

            $days[] = array_merge($day, [
                'orderable' => $orderable,
                'order_block_reason' => $orderBlockReason,
                'is_today' => $date->format('Y-m-d') === $today->format('Y-m-d'),
                'cutoff_at' => $this->orderCutoffService->getCutoffForDate($date)->format(\DateTimeInterface::ATOM),
            ]);
        }

        return [
            'days' => $days,
            'cutoff_hour' => $this->cutoffHour,
            'timezone' => $this->timezone,
            'today' => $today->format('Y-m-d'),
        ];
    }
}
