<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\OrderStatusService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** @extends AbstractCrudController<Order> */
final class OrderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Заказ')
            ->setEntityLabelInPlural('Заказы')
            ->setDefaultSort(['pickupDate' => 'DESC', 'humanNumber' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $confirmPayment = Action::new('confirmPayment', 'Подтвердить оплату', 'fa fa-check')
            ->linkToCrudAction('confirmPayment')
            ->displayIf(static fn (Order $order): bool => $order->getStatus() === OrderStatus::PendingPayment);

        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $confirmPayment)
            ->add(Crud::PAGE_DETAIL, $confirmPayment);
    }

    #[AdminRoute(path: '/{entityId}/confirm-payment', name: 'confirm_payment', options: ['methods' => ['POST']])]
    public function confirmPayment(AdminContext $context): RedirectResponse
    {
        /** @var Order $order */
        $order = $context->getEntity()->getInstance();
        $this->orderStatusService->confirmPayment($order);
        $this->addFlash('success', sprintf('Заказ #%d: оплата подтверждена.', $order->getHumanNumber()));

        $referrer = $context->getRequest()->headers->get('referer');

        return $this->redirect($referrer ?: $this->generateUrl('admin'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('humanNumber', '№');
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
        yield DateTimeField::new('paymentClaimedAt', 'Клиент: оплатил')->hideOnIndex();
        yield TextareaField::new('comment', 'Комментарий')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создан')->hideOnIndex();
    }
}
