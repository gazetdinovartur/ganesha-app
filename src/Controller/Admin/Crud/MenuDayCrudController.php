<?php

namespace App\Controller\Admin\Crud;

use App\Entity\MenuDay;
use App\Service\MenuDayService;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<MenuDay> */
final class MenuDayCrudController extends AbstractCrudController
{
    private string $indexRange = 'week';

    private \DateTimeImmutable $indexPeriodStart;

    private \DateTimeImmutable $indexPeriodEnd;

    private bool $indexPeriodFilterActive = false;

    public function __construct(
        private readonly MenuDayService $menuDayService,
        #[Autowire(param: 'app.timezone')]
        private readonly string $timezone,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MenuDay::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('День меню')
            ->setEntityLabelInPlural('Дни меню')
            ->setPageTitle(Crud::PAGE_INDEX, 'Дни меню')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (MenuDay $menuDay): string => sprintf('День меню: %s', $menuDay->getDate()->format('d.m.Y')))
            ->setDefaultSort(['date' => 'ASC'])
            ->overrideTemplate('crud/index', 'admin/crud/menu_day_index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT);
    }

    public function index(AdminContext $context): KeyValueStore|Response
    {
        $this->resolveIndexPeriod($context->getRequest());

        $days = $this->indexPeriodStart->diff($this->indexPeriodEnd)->days + 1;
        $this->menuDayService->ensureDaysFrom($this->indexPeriodStart, $days);
        $this->menuDayService->ensureMonthFromToday();

        return parent::index($context);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $parameters = parent::configureResponseParameters($responseParameters);

        if ($parameters->get('pageName') === Crud::PAGE_INDEX) {
            $parameters->set('menuDayPeriod', $this->buildPeriodNavigation());
        }

        return $parameters;
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateField::new('date', 'Дата')
            ->setTemplatePath('admin/field/menu_day_date_link.html.twig');
        yield IntegerField::new('id', 'Позиций')
            ->setTextAlign('center')
            ->formatValue(fn (?int $value, MenuDay $entity): int => $entity->getDishes()->count());
        yield BooleanField::new('isPublished', 'Опубликован')
            ->setTextAlign('right');
        yield TextareaField::new('note', 'Заметка')->hideOnIndex();
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if (!$this->indexPeriodFilterActive) {
            return $qb;
        }

        $alias = $qb->getRootAliases()[0] ?? 'entity';
        $qb->andWhere(sprintf('%s.date >= :periodFrom', $alias))
            ->andWhere(sprintf('%s.date <= :periodTo', $alias))
            ->setParameter('periodFrom', $this->indexPeriodStart)
            ->setParameter('periodTo', $this->indexPeriodEnd);

        return $qb;
    }

    private function resolveIndexPeriod(Request $request): void
    {
        $timezone = new \DateTimeZone($this->timezone);
        $today = new \DateTimeImmutable('today', $timezone);
        $currentWeekStart = $today->modify('monday this week');

        $range = $request->query->get('range');
        $this->indexRange = $range === 'month' ? 'month' : 'week';

        $startParam = $request->query->get('start');
        $anchor = (\is_string($startParam) && $startParam !== '')
            ? new \DateTimeImmutable($startParam, $timezone)
            : ($this->indexRange === 'month' ? $today : $currentWeekStart);

        if ($this->indexRange === 'month') {
            $this->indexPeriodStart = $anchor->modify('first day of this month');
            $this->indexPeriodEnd = $anchor->modify('last day of this month');
        } else {
            $weekStart = $anchor->modify('monday this week');
            $this->indexPeriodStart = $weekStart;
            $this->indexPeriodEnd = $weekStart->modify('+6 days');
        }

        $this->indexPeriodFilterActive = $this->isDefaultIndexView($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPeriodNavigation(): array
    {
        $timezone = new \DateTimeZone($this->timezone);
        $today = new \DateTimeImmutable('today', $timezone);
        $currentWeekStart = $today->modify('monday this week');
        $currentMonthStart = $today->modify('first day of this month');

        if ($this->indexRange === 'month') {
            $monthStart = $this->indexPeriodStart;
            $prevMonthStart = $monthStart->modify('-1 month')->modify('first day of this month');
            $nextMonthStart = $monthStart->modify('+1 month')->modify('first day of this month');
            $prevWeekStart = $monthStart->modify('-1 day')->modify('monday this week');
            $nextWeekStart = $this->indexPeriodEnd->modify('+1 day')->modify('monday this week');
        } else {
            $weekStart = $this->indexPeriodStart;
            $prevWeekStart = $weekStart->modify('-7 days');
            $nextWeekStart = $weekStart->modify('+7 days');
            $monthAnchor = $weekStart->modify('first day of this month');
            $prevMonthStart = $monthAnchor->modify('-1 month')->modify('first day of this month');
            $nextMonthStart = $monthAnchor->modify('+1 month')->modify('first day of this month');
        }

        return [
            'range' => $this->indexRange,
            'periodStart' => $this->indexPeriodStart,
            'periodEnd' => $this->indexPeriodEnd,
            'periodLabel' => $this->indexRange === 'month'
                ? $this->formatMonthLabel($this->indexPeriodStart)
                : sprintf(
                    'Неделя %s–%s',
                    $this->indexPeriodStart->format('d.m'),
                    $this->indexPeriodEnd->format('d.m.Y'),
                ),
            'prevWeekStart' => $prevWeekStart->format('Y-m-d'),
            'nextWeekStart' => $nextWeekStart->format('Y-m-d'),
            'prevMonthStart' => $prevMonthStart->format('Y-m-d'),
            'nextMonthStart' => $nextMonthStart->format('Y-m-d'),
            'isCurrentWeek' => $this->indexRange === 'week'
                && $this->indexPeriodStart->format('Y-m-d') === $currentWeekStart->format('Y-m-d'),
            'isCurrentMonth' => $this->indexRange === 'month'
                && $this->indexPeriodStart->format('Y-m-d') === $currentMonthStart->format('Y-m-d'),
            'filterActive' => $this->indexPeriodFilterActive,
        ];
    }

    private function formatMonthLabel(\DateTimeImmutable $monthStart): string
    {
        static $months = [
            1 => 'Январь',
            2 => 'Февраль',
            3 => 'Март',
            4 => 'Апрель',
            5 => 'Май',
            6 => 'Июнь',
            7 => 'Июль',
            8 => 'Август',
            9 => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь',
        ];

        $month = (int) $monthStart->format('n');

        return ($months[$month] ?? $monthStart->format('m')).' '.$monthStart->format('Y');
    }

    private function isDefaultIndexView(Request $request): bool
    {
        if ($request->query->has('query') && trim((string) $request->query->get('query')) !== '') {
            return false;
        }

        if ($request->query->has('filters') && $request->query->all('filters') !== []) {
            return false;
        }

        return true;
    }
}
