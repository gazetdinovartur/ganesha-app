<?php

namespace App\Controller\Admin\Crud;

use App\Entity\MenuDay;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

/** @extends AbstractCrudController<MenuDay> */
final class MenuDayCrudController extends AbstractCrudController
{
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
            ->setDefaultSort(['date' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $generateMonth = Action::new('generateMonth', 'Сгенерировать месяц', 'fa fa-calendar-days')
            ->createAsGlobalAction()
            ->setLabel('Сгенерировать новый месяц')
            ->linkToRoute('admin_menu_day_generate', ['range' => 'month']);

        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->add(Crud::PAGE_INDEX, $generateMonth);
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

        $isDefaultView = $searchDto->getQuery() === ''
            && $searchDto->getCustomSort() === []
            && ($searchDto->getAppliedFilters() === null || $searchDto->getAppliedFilters() === []);

        if (!$isDefaultView) {
            return $qb;
        }

        $alias = $qb->getRootAliases()[0] ?? 'entity';
        $weekStart = (new \DateTimeImmutable('today'))->modify('monday this week');

        $qb->resetDQLPart('orderBy');
        $qb->addSelect(sprintf('CASE WHEN %s.date < :weekStart THEN 1 ELSE 0 END AS HIDDEN week_bucket', $alias));
        $qb->setParameter('weekStart', $weekStart);
        $qb->addOrderBy('week_bucket', 'ASC');
        $qb->addOrderBy(sprintf('%s.date', $alias), 'ASC');

        return $qb;
    }
}
