<?php

namespace App\Service;

use App\Exception\OrderCreationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderCutoffService
{
    public function __construct(
        #[Autowire(param: 'app.timezone')]
        private readonly string $timezone,
        #[Autowire(param: 'app.order_cutoff_hour')]
        private readonly int $cutoffHour,
        #[Autowire(param: 'app.order_menu_horizon_days')]
        private readonly int $menuHorizonDays,
    ) {
    }

    public function assertCanOrderForDate(\DateTimeImmutable $pickupDate, ?\DateTimeImmutable $now = null): void
    {
        $now = $this->normalizeNow($now);
        $pickupDate = $this->normalizeDate($pickupDate);

        if ($pickupDate < $now->setTime(0, 0)) {
            throw new OrderCreationException('Дата самовывоза уже прошла.', 422, 'pickup_date_past');
        }

        if (!$this->isWithinMenuHorizon($pickupDate, $now)) {
            throw new OrderCreationException(
                sprintf('Можно заказать не более чем на %d дней вперёд.', $this->menuHorizonDays),
                422,
                'pickup_date_out_of_horizon',
            );
        }

        if ($now >= $this->getCutoffForDate($pickupDate)) {
            throw new OrderCreationException(
                sprintf('Приём заказов на %s закрыт в %02d:00 предыдущего дня.', $pickupDate->format('d.m.Y'), $this->cutoffHour),
                422,
                'cutoff_passed',
            );
        }
    }

    public function getCutoffForDate(\DateTimeImmutable $pickupDate): \DateTimeImmutable
    {
        $pickupDate = $this->normalizeDate($pickupDate);

        return $pickupDate->modify('-1 day')->setTime($this->cutoffHour, 0);
    }

    public function isWithinMenuHorizon(\DateTimeImmutable $pickupDate, ?\DateTimeImmutable $now = null): bool
    {
        $now = $this->normalizeNow($now);
        $today = $now->setTime(0, 0);
        $pickupDate = $this->normalizeDate($pickupDate);
        $maxDate = $today->modify(sprintf('+%d days', $this->menuHorizonDays - 1));

        return $pickupDate >= $today && $pickupDate <= $maxDate;
    }

    private function normalizeDate(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone($this->timezone))->setTime(0, 0);
    }

    private function normalizeNow(?\DateTimeImmutable $now): \DateTimeImmutable
    {
        $now ??= new \DateTimeImmutable();

        return $now->setTimezone(new \DateTimeZone($this->timezone));
    }
}
