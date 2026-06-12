<?php

namespace App\Controller\Admin\Crud;

use App\Entity\PickupPoint;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<PickupPoint> */
final class PickupPointCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PickupPoint::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Точка выдачи')
            ->setEntityLabelInPlural('Точки выдачи')
            ->setPageTitle(Crud::PAGE_INDEX, 'Точки выдачи')
            ->setPageTitle(Crud::PAGE_NEW, 'Новая точка выдачи')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (PickupPoint $pickupPoint): string => sprintf('Редактирование: %s', $pickupPoint->getName() ?: 'точка выдачи'))
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (PickupPoint $pickupPoint): string => sprintf('Точка выдачи: %s', $pickupPoint->getName() ?: '—'))
            ->setFormThemes([
                'form/admin_theme.html.twig',
                '@EasyAdmin/crud/form_theme.html.twig',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Название')->setColumns(12);
        yield TextField::new('address', 'Адрес')->setColumns(12);
        yield TextField::new('pickupHours', 'Часы выдачи')->setColumns(12);
        yield TextareaField::new('description', 'Описание')->hideOnIndex()->setColumns(12);
        yield BooleanField::new('isActive', 'Активна')->setColumns(12);
    }
}
