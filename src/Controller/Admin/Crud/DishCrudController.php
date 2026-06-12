<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Dish;
use App\Form\Admin\DishFormType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Dish> */
final class DishCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Dish::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Блюдо')
            ->setEntityLabelInPlural('Блюда')
            ->setDefaultSort(['sortOrder' => 'ASC', 'name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function createNewFormType(): string
    {
        return DishFormType::class;
    }

    public function createEditFormType(): string
    {
        return DishFormType::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Название');
        yield IntegerField::new('price', 'Цена, коп.')
            ->formatValue(fn (?int $value): string => $value === null ? '—' : number_format($value / 100, 2, ',', ' ').' ₽');
        yield BooleanField::new('isActive', 'Активно');
        yield IntegerField::new('sortOrder', 'Порядок');

        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('shortDescription', 'Описание');
            yield TextField::new('photoPath', 'Фото');
        }
    }
}
