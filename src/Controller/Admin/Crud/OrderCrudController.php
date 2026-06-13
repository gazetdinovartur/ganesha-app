<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Order;
use App\Enum\OrderChannel;
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
use Symfony\Component\Uid\Uuid;

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
            ->setDefaultRowAction(Action::DETAIL)
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
            ->setChoices($this->orderStatusChoices())
            ->renderAsBadges($this->orderStatusBadgeMap());
        yield IntegerField::new('totalAmount', 'Сумма')
            ->formatValue(fn (?int $v): string => $v === null ? '—' : number_format($v / 100, 2, ',', ' ').' ₽');
        yield ChoiceField::new('channel', 'Канал')
            ->setChoices(array_combine(
                array_map(static fn (OrderChannel $c) => $c->label(), OrderChannel::cases()),
                array_map(static fn (OrderChannel $c) => $c->value, OrderChannel::cases()),
            ));
        yield TextField::new('paymentGroupUuid', 'Группа оплаты')
            ->formatValue(static fn (?Uuid $uuid): string => $uuid === null ? '—' : (string) $uuid)
            ->hideOnIndex();
        yield DateTimeField::new('paidAt', 'Оплачен')->hideOnIndex();
        yield TextareaField::new('comment', 'Комментарий')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создан')->hideOnIndex();
    }

    /**
     * @return array<string, string>
     */
    private function orderStatusChoices(): array
    {
        $choices = [];
        foreach (OrderStatus::cases() as $status) {
            $choices[$status->label()] = $status->value;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function orderStatusBadgeMap(): array
    {
        $badges = [];
        foreach (OrderStatus::cases() as $status) {
            $badges[$status->value] = $status->badgeType();
        }

        return $badges;
    }
}
