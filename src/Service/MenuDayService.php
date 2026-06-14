<?php

namespace App\Service;

use App\Entity\MenuDay;
use App\Repository\MenuDayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MenuDayService
{
    private const DEFAULT_MONTH_DAYS = 30;

    public function __construct(
        private readonly MenuDayRepository $menuDayRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.timezone')]
        private readonly string $timezone,
    ) {
    }

    public function ensureMonthFromToday(?\DateTimeImmutable $today = null): void
    {
        $today ??= new \DateTimeImmutable('today', new \DateTimeZone($this->timezone));
        $this->ensureDaysFrom($today, self::DEFAULT_MONTH_DAYS);
    }

    /**
     * @return list<MenuDay>
     */
    public function ensureWeekFrom(\DateTimeImmutable $startDate, int $days = 7): array
    {
        return $this->ensureDaysFrom($startDate, min(7, $days));
    }

    /**
     * @return list<MenuDay>
     */
    public function ensureDaysFrom(\DateTimeImmutable $startDate, int $days): array
    {
        $days = max(1, min(62, $days));
        $startDate = $startDate->setTime(0, 0);
        $endDate = $startDate->modify(sprintf('+%d days', $days - 1));

        /** @var array<string, MenuDay> $existingByDate */
        $existingByDate = [];
        foreach ($this->menuDayRepository->findBetween($startDate, $endDate) as $menuDay) {
            $existingByDate[$menuDay->getDate()->format('Y-m-d')] = $menuDay;
        }

        $result = [];
        $needsFlush = false;

        for ($i = 0; $i < $days; ++$i) {
            $date = $startDate->modify(sprintf('+%d days', $i));
            $key = $date->format('Y-m-d');

            if (isset($existingByDate[$key])) {
                $result[] = $existingByDate[$key];
                continue;
            }

            $menuDay = (new MenuDay())->setDate($date);
            $this->entityManager->persist($menuDay);
            $existingByDate[$key] = $menuDay;
            $result[] = $menuDay;
            $needsFlush = true;
        }

        if ($needsFlush) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
