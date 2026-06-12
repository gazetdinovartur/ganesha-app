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
            ->setEntityLabelInPlural('Точки выдачи');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Название');
        yield TextField::new('address', 'Адрес');
        yield TextField::new('pickupHours', 'Часы выдачи');
        yield TextareaField::new('description', 'Описание')->hideOnIndex();
        yield BooleanField::new('isActive', 'Активна');
    }
}
