<?php

namespace App\Command;

use App\Entity\Dish;
use App\Entity\DishCategory;
use App\Entity\MenuDay;
use App\Entity\MenuDayDish;
use App\Repository\DishCategoryRepository;
use App\Repository\DishRepository;
use App\Repository\MenuDayRepository;
use App\Service\MenuDayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:seed:menu',
    description: 'Демо-меню: категории, блюда и опубликованные дни на ближайшую неделю',
)]
final class SeedMenuCommand extends Command
{
    /** @var array<string, array{sort: int}> */
    private const array CATEGORIES = [
        'Супы' => ['sort' => 10],
        'Горячее' => ['sort' => 20],
        'Салаты' => ['sort' => 30],
        'Выпечка' => ['sort' => 40],
        'Десерты' => ['sort' => 50],
        'Напитки' => ['sort' => 60],
    ];

    /**
     * @var list<array{
     *     name: string,
     *     category: string,
     *     price_rub: int,
     *     description: string,
     *     weight_g: int,
     *     ingredients: list<string>,
     *     allergens: list<string>,
     *     note: string|null,
     *     sort: int
     * }>
     */
    private const array DISHES = [
        [
            'name' => 'Суп из чечевицы с куркумой',
            'category' => 'Супы',
            'price_rub' => 320,
            'description' => 'Согревающий суп с красной чечевицей, куркумой и лимоном',
            'weight_g' => 350,
            'ingredients' => ['чечевица', 'морковь', 'лук', 'куркума', 'лимон'],
            'allergens' => [],
            'note' => 'лёгкий, согревающий',
            'sort' => 10,
        ],
        [
            'name' => 'Том-ям веганский с тофу',
            'category' => 'Супы',
            'price_rub' => 380,
            'description' => 'Острый бульон с лемонграссом, шампиньонами',
            'weight_g' => 380,
            'ingredients' => ['тофу', 'шампиньоны', 'лемонграсс', 'лайм', 'чили'],
            'allergens' => ['соя'],
            'note' => 'острый, можно без чили',
            'sort' => 20,
        ],
        [
            'name' => 'Крем-суп из тыквы с имбирём',
            'category' => 'Супы',
            'price_rub' => 340,
            'description' => 'Нежный крем-суп с кокосовым молоком и имбирём',
            'weight_g' => 360,
            'ingredients' => ['тыква', 'имбирь', 'кокосовое молоко', 'лук'],
            'allergens' => [],
            'note' => 'мягкий, осенний',
            'sort' => 30,
        ],
        [
            'name' => 'Палак-панир',
            'category' => 'Горячее',
            'price_rub' => 420,
            'description' => 'Шпинатное рагу с домашним сыром панир',
            'weight_g' => 320,
            'ingredients' => ['шпинат', 'панир', 'томат', 'специи garam masala'],
            'allergens' => ['молоко'],
            'note' => 'классика индийской кухни',
            'sort' => 10,
        ],
        [
            'name' => 'Рис басмати с овощами и кешью',
            'category' => 'Горячее',
            'price_rub' => 390,
            'description' => 'Ароматный рис с цукини, перцем и обжаренным кешью',
            'weight_g' => 380,
            'ingredients' => ['рис басмати', 'цукини', 'перец', 'кешью', 'куркума'],
            'allergens' => ['орехи'],
            'note' => 'сытно и сбалансированно',
            'sort' => 20,
        ],
        [
            'name' => 'Кичри с сезонными овощами',
            'category' => 'Горячее',
            'price_rub' => 360,
            'description' => 'Рис, маш и овощи — ayurvedic comfort food',
            'weight_g' => 400,
            'ingredients' => ['рис', 'маш', 'морковь', 'кабачок', 'кумин'],
            'allergens' => [],
            'note' => 'легко усваивается',
            'sort' => 30,
        ],
        [
            'name' => 'Паста primavera с цукини',
            'category' => 'Горячее',
            'price_rub' => 410,
            'description' => 'Паста с сезонными овощами и базиликом',
            'weight_g' => 370,
            'ingredients' => ['паста', 'цукини', 'брокколи', 'томат', 'базилик'],
            'allergens' => ['глютен'],
            'note' => 'средиземноморский день',
            'sort' => 40,
        ],
        [
            'name' => 'Будда-бowl с киноа и тахини',
            'category' => 'Горячее',
            'price_rub' => 450,
            'description' => 'Киноа, запечённые овощи, нут и соус тахини',
            'weight_g' => 420,
            'ingredients' => ['киноа', 'нут', 'свёкла', 'морковь', 'тахини'],
            'allergens' => ['кунжут'],
            'note' => 'белковый, сытный',
            'sort' => 50,
        ],
        [
            'name' => 'Салат из свежих овощей с хумусом',
            'category' => 'Салаты',
            'price_rub' => 320,
            'description' => 'Хрустящие овощи, хумус и зелёные травы',
            'weight_g' => 280,
            'ingredients' => ['огурец', 'помидор', 'редис', 'хумус', 'укроп'],
            'allergens' => ['кунжут'],
            'note' => 'свежий, без заправки маслом',
            'sort' => 10,
        ],
        [
            'name' => 'Салат с запечённой свёклой и фетой',
            'category' => 'Салаты',
            'price_rub' => 340,
            'description' => 'Свёкла, фета, грецкий орех и бalsamic',
            'weight_g' => 260,
            'ingredients' => ['свёкла', 'фета', 'грецкий орех', 'руккола'],
            'allergens' => ['молоко', 'орехи'],
            'note' => 'яркий, питательный',
            'sort' => 20,
        ],
        [
            'name' => 'Цельнозерновой хлеб с семенами',
            'category' => 'Выпечка',
            'price_rub' => 120,
            'description' => 'Домашний хлеб с льном, подсолнечником и тыквенными семечками',
            'weight_g' => 80,
            'ingredients' => ['цельнозерновая мука', 'семена льна', 'семена подсолнечника'],
            'allergens' => ['глютен'],
            'note' => 'к порции супа',
            'sort' => 10,
        ],
        [
            'name' => 'Лепёшка с зеленью',
            'category' => 'Выпечка',
            'price_rub' => 140,
            'description' => 'Тонкая лепёшка с укропом и петрушкой',
            'weight_g' => 90,
            'ingredients' => ['мука', 'укроп', 'петрушка', 'оливковое масло'],
            'allergens' => ['глютен'],
            'note' => 'свежее из печи',
            'sort' => 20,
        ],
        [
            'name' => 'Шоколадный брауни на финиках',
            'category' => 'Десерты',
            'price_rub' => 280,
            'description' => 'Плотный брауни без рафинированного сахара',
            'weight_g' => 120,
            'ingredients' => ['финики', 'какао', 'миндаль', 'кокосовое масло'],
            'allergens' => ['орехи'],
            'note' => 'нежно сладкий',
            'sort' => 10,
        ],
        [
            'name' => 'Рисовая каша с манго',
            'category' => 'Десерты',
            'price_rub' => 260,
            'description' => 'Кремовая рисовая каша с манго и кокосом',
            'weight_g' => 200,
            'ingredients' => ['рис', 'манго', 'кокосовое молоко', 'ваниль'],
            'allergens' => [],
            'note' => 'лёгкий десерт',
            'sort' => 20,
        ],
        [
            'name' => 'Аyran с мятой',
            'category' => 'Напитки',
            'price_rub' => 150,
            'description' => 'Освежающий йогуртовый напиток с мятой',
            'weight_g' => 300,
            'ingredients' => ['йогурт', 'мята', 'соль'],
            'allergens' => ['молоко'],
            'note' => 'к горячему',
            'sort' => 10,
        ],
        [
            'name' => 'Чай масала домашний',
            'category' => 'Напитки',
            'price_rub' => 180,
            'description' => 'Чёрный чай с кардамоном, имбирём и корицей',
            'weight_g' => 300,
            'ingredients' => ['чай', 'имбирь', 'кардамон', 'корица', 'молоко'],
            'allergens' => ['молоко'],
            'note' => 'согревающий',
            'sort' => 20,
        ],
    ];

    /**
     * @var list<array{note: string, dishes: list<string>}>
     */
    private const array WEEK_PLAN = [
        [
            'note' => 'Лёгкий понедельник — суп, салат и свежий хлеб',
            'dishes' => [
                'Суп из чечевицы с куркумой',
                'Салат из свежих овощей с хумусом',
                'Цельнозерновой хлеб с семенами',
                'Чай масала домашний',
            ],
        ],
        [
            'note' => 'Индийские мотивы: кичри, палак и сладкое манго',
            'dishes' => [
                'Кичри с сезонными овощами',
                'Палак-панир',
                'Рисовая каша с манго',
                'Аyran с мятой',
            ],
        ],
        [
            'note' => 'Азиатский день — том-ям и bowl с тахини',
            'dishes' => [
                'Том-ям веганский с тофу',
                'Будда-bowl с киноа и тахини',
                'Лепёшка с зеленью',
            ],
        ],
        [
            'note' => 'Средиземноморское меню',
            'dishes' => [
                'Крем-суп из тыквы с имбирём',
                'Паста primavera с цукини',
                'Салат с запечённой свёклой и фетой',
            ],
        ],
        [
            'note' => 'Комфорт-фуд: рис, чечевица и брауни',
            'dishes' => [
                'Суп из чечевицы с куркумой',
                'Рис басмати с овощами и кешью',
                'Шоколадный брауни на финиках',
            ],
        ],
        [
            'note' => 'Зелёный день — bowl, салат и свежая выпечка',
            'dishes' => [
                'Будда-bowl с киноа и тахини',
                'Салат из свежих овощей с хумусом',
                'Лепёшка с зеленью',
                'Аyran с мятой',
            ],
        ],
        [
            'note' => 'Разнообразная пятница — выбирайте любимое',
            'dishes' => [
                'Том-ям веганский с тофу',
                'Палак-панир',
                'Кичри с сезонными овощами',
                'Салат с запечённой свёклой и фетой',
                'Шоколадный брауни на финиках',
                'Чай масала домашний',
            ],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuDayService $menuDayService,
        private readonly DishCategoryRepository $dishCategoryRepository,
        private readonly DishRepository $dishRepository,
        private readonly MenuDayRepository $menuDayRepository,
        #[Autowire(param: 'app.timezone')]
        private readonly string $timezone,
        #[Autowire(param: 'app.order_menu_horizon_days')]
        private readonly int $menuHorizonDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Пересобрать состав уже опубликованных дней')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Сколько дней меню создать', (string) $this->menuHorizonDays);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $days = max(1, min(14, (int) $input->getOption('days')));

        $today = new \DateTimeImmutable('today', new \DateTimeZone($this->timezone));
        $io->title(sprintf('Демо-меню Ganesha · %s → +%d дн.', $today->format('d.m.Y'), $days - 1));

        $categories = $this->seedCategories($io);
        $dishesByName = $this->seedDishes($categories, $io);
        $menuDays = $this->menuDayService->ensureDaysFrom($today, $days);
        $published = $this->seedMenuDays($menuDays, $dishesByName, $force, $io);

        $this->entityManager->flush();

        $io->success(sprintf(
            'Готово: %d категорий, %d блюд, %d опубликованных дней меню.',
            \count($categories),
            \count($dishesByName),
            $published,
        ));
        $io->listing([
            'Сайт: http://localhost:8080/',
            'Меню API: curl -s http://localhost:8080/api/menu | jq',
            'Повторный seed: php bin/console app:seed:menu --force',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, DishCategory>
     */
    private function seedCategories(SymfonyStyle $io): array
    {
        $result = [];
        $created = 0;

        foreach (self::CATEGORIES as $name => $meta) {
            $category = $this->dishCategoryRepository->findOneBy(['name' => $name]);
            if ($category === null) {
                $category = (new DishCategory())
                    ->setName($name)
                    ->setSortOrder($meta['sort']);
                $this->entityManager->persist($category);
                ++$created;
            } else {
                $category->setSortOrder($meta['sort']);
            }

            $result[$name] = $category;
        }

        $this->entityManager->flush();
        $io->text(sprintf('Категории: %d (%d новых).', \count($result), $created));

        return $result;
    }

    /**
     * @param array<string, DishCategory> $categories
     *
     * @return array<string, Dish>
     */
    private function seedDishes(array $categories, SymfonyStyle $io): array
    {
        $result = [];
        $created = 0;

        foreach (self::DISHES as $row) {
            $dish = $this->dishRepository->findOneBy(['name' => $row['name']]);
            if ($dish === null) {
                $dish = new Dish();
                $this->entityManager->persist($dish);
                ++$created;
            }

            $dish
                ->setName($row['name'])
                ->setShortDescription($row['description'])
                ->setPrice($row['price_rub'] * 100)
                ->setSortOrder($row['sort'])
                ->setIsActive(true)
                ->setCategory($categories[$row['category']] ?? null)
                ->setComposition([
                    'weight_g' => $row['weight_g'],
                    'ingredients' => $row['ingredients'],
                    'allergens' => $row['allergens'],
                    'note' => $row['note'],
                ]);

            $result[$row['name']] = $dish;
        }

        $this->entityManager->flush();
        $io->text(sprintf('Блюда: %d (%d новых).', \count($result), $created));

        return $result;
    }

    /**
     * @param list<MenuDay>          $menuDays
     * @param array<string, Dish>    $dishesByName
     */
    private function seedMenuDays(array $menuDays, array $dishesByName, bool $force, SymfonyStyle $io): int
    {
        $published = 0;

        foreach ($menuDays as $index => $menuDay) {
            $plan = self::WEEK_PLAN[$index % \count(self::WEEK_PLAN)];

            if ($menuDay->isPublished() && !$menuDay->getDishes()->isEmpty() && !$force) {
                $io->note(sprintf(
                    '%s — уже опубликован (%d блюд), пропуск. Используйте --force.',
                    $menuDay->getDate()->format('d.m.Y'),
                    $menuDay->getDishes()->count(),
                ));
                continue;
            }

            $this->clearMenuDayDishes($menuDay);

            $menuDay
                ->setIsPublished(true)
                ->setNote($plan['note']);

            $sort = 10;
            foreach ($plan['dishes'] as $dishName) {
                $dish = $dishesByName[$dishName] ?? null;
                if ($dish === null) {
                    continue;
                }

                $menuDayDish = (new MenuDayDish())
                    ->setDish($dish)
                    ->setSortOrder($sort)
                    ->setIsAvailable(true)
                    ->setOrderedPortions(0);

                $menuDay->addDish($menuDayDish);
                $this->entityManager->persist($menuDayDish);
                $sort += 10;
            }

            ++$published;
            $io->text(sprintf(
                '✓ %s — %d блюд · %s',
                $menuDay->getDate()->format('d.m.Y (D)'),
                $menuDay->getDishes()->count(),
                $plan['note'],
            ));
        }

        return $published;
    }

    private function clearMenuDayDishes(MenuDay $menuDay): void
    {
        foreach ($menuDay->getDishes()->toArray() as $menuDayDish) {
            $menuDay->removeDish($menuDayDish);
            $this->entityManager->remove($menuDayDish);
        }
    }
}
