<?php

namespace App\Service;

use App\Entity\MenuDay;
use App\Repository\MenuDayRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MenuDayService
{
    public function __construct(
        private readonly MenuDayRepository $menuDayRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<MenuDay>
     */
    public function ensureWeekFrom(\DateTimeImmutable $startDate, int $days = 7): array
    {
        $days = max(1, min(7, $days));
        $result = [];

        for ($i = 0; $i < $days; ++$i) {
            $date = $startDate->modify(sprintf('+%d days', $i));
            $menuDay = $this->menuDayRepository->findOneBy(['date' => $date]);

            if ($menuDay === null) {
                $menuDay = (new MenuDay())->setDate($date);
                $this->entityManager->persist($menuDay);
            }

            $result[] = $menuDay;
        }

        $this->entityManager->flush();

        return $result;
    }
}
