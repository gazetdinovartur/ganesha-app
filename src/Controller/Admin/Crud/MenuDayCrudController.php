<?php

namespace App\Controller\Admin\Crud;

use App\Entity\MenuDay;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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
            ->setDefaultSort(['date' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $editMenu = Action::new('editMenu', 'Редактировать', 'fa fa-edit')
            ->linkToRoute('admin_menu_day_edit', fn (MenuDay $menuDay) => ['id' => $menuDay->getId()]);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $editMenu)
            ->add(Crud::PAGE_DETAIL, $editMenu);
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateField::new('date', 'Дата');
        yield BooleanField::new('isPublished', 'Опубликован');
        yield IntegerField::new('id', 'Позиций')
            ->formatValue(fn (?int $value, MenuDay $entity): int => $entity->getDishes()->count());
        yield TextareaField::new('note', 'Заметка')->hideOnIndex();
    }
}
