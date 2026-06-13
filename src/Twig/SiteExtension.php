<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class SiteExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money_rub', $this->formatMoneyRub(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('weekday_ru', $this->weekdayRu(...)),
            new TwigFunction('day_label_ru', $this->dayLabelRu(...)),
            new TwigFunction('plural_ru', $this->pluralRu(...)),
        ];
    }

    public function formatMoneyRub(int $kopecks): string
    {
        return number_format($kopecks / 100, 0, ',', ' ').' ₽';
    }

    public function weekdayRu(\DateTimeInterface|string $date): string
    {
        if (\is_string($date)) {
            $date = new \DateTimeImmutable($date);
        }

        static $names = [
            1 => 'пн',
            2 => 'вт',
            3 => 'ср',
            4 => 'чт',
            5 => 'пт',
            6 => 'сб',
            7 => 'вс',
        ];

        return $names[(int) $date->format('N')] ?? $date->format('D');
    }

    public function dayLabelRu(\DateTimeInterface|string $date): string
    {
        if (\is_string($date)) {
            $date = new \DateTimeImmutable($date);
        }

        static $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        $month = (int) $date->format('n');

        return sprintf('%d %s', (int) $date->format('j'), $months[$month] ?? $date->format('F'));
    }

    /**
     * Склонение по числу: 1 заказ, 2 заказа, 5 заказов, 21 заказ.
     */
    public function pluralRu(int $number, string $one, string $few, string $many): string
    {
        $mod100 = abs($number) % 100;
        $mod10 = $mod100 % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        if ($mod10 === 1) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }

        return $many;
    }
}
