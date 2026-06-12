<?php

namespace App\Service;

use App\Repository\MenuDayRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MenuCatalogService
{
    public function __construct(
        private readonly MenuDayRepository $menuDayRepository,
        #[Autowire(param: 'app.timezone')]
        private readonly string $timezone,
        #[Autowire(param: 'app.order_menu_horizon_days')]
        private readonly int $menuHorizonDays,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPublishedMenu(?\DateTimeImmutable $from = null): array
    {
        $from ??= new \DateTimeImmutable('today', new \DateTimeZone($this->timezone));
        $from = $from->setTimezone(new \DateTimeZone($this->timezone))->setTime(0, 0);
        $to = $from->modify(sprintf('+%d days', $this->menuHorizonDays - 1));

        $days = [];
        foreach ($this->menuDayRepository->findPublishedBetween($from, $to) as $menuDay) {
            $dishes = [];
            foreach ($menuDay->getDishes() as $menuDayDish) {
                if (!$menuDayDish->isAvailable()) {
                    continue;
                }

                $dish = $menuDayDish->getDish();
                if ($dish === null || !$dish->isActive()) {
                    continue;
                }

                $composition = $dish->getComposition();
                $dishes[] = [
                    'menu_day_dish_id' => $menuDayDish->getId(),
                    'dish_id' => $dish->getId(),
                    'name' => $dish->getName(),
                    'short_description' => $dish->getShortDescription(),
                    'price' => $menuDayDish->getEffectivePrice(),
                    'photo_path' => $dish->getPhotoPath(),
                    'category' => $dish->getCategory()?->getName(),
                    'weight_g' => $composition['weight_g'] ?? null,
                    'ingredients' => $composition['ingredients'] ?? [],
                    'allergens' => $composition['allergens'] ?? [],
                ];
            }

            if ($dishes === []) {
                continue;
            }

            $days[] = [
                'date' => $menuDay->getDate()->format('Y-m-d'),
                'note' => $menuDay->getNote(),
                'dishes' => $dishes,
            ];
        }

        return $days;
    }
}
