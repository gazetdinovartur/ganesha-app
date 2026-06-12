<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Order;
use App\Enum\OrderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Order> */
final class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Заказ')
            ->setEntityLabelInPlural('Заказы')
            ->setPageTitle(Crud::PAGE_INDEX, 'Заказы')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Order $order): string => sprintf('Заказ №%d', $order->getHumanNumber()))
            ->setDefaultSort(['pickupDate' => 'DESC', 'humanNumber' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('humanNumber', '№');
        yield TextField::new('uuid', 'UUID')->hideOnIndex();
        yield DateField::new('pickupDate', 'День выдачи');
        yield AssociationField::new('customer', 'Клиент');
        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (OrderStatus $s) => $s->label(), OrderStatus::cases()),
                OrderStatus::cases(),
            ));
        yield IntegerField::new('totalAmount', 'Сумма')
            ->formatValue(fn (?int $v): string => $v === null ? '—' : number_format($v / 100, 2, ',', ' ').' ₽');
        yield TextField::new('channel', 'Канал');
        yield DateTimeField::new('paidAt', 'Оплачен')->hideOnIndex();
        yield TextareaField::new('comment', 'Комментарий')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создан')->hideOnIndex();
    }
}
