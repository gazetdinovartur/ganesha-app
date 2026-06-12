<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Crud\OrderCrudController;
use App\Entity\AdminUser;
use App\Entity\Customer;
use App\Entity\Dish;
use App\Entity\MenuDay;
use App\Entity\Order;
use App\Entity\PickupPoint;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'ordersUrl' => $this->adminUrlGenerator
                ->setController(OrderCrudController::class)
                ->setAction('index')
                ->generateUrl(),
        ]);
    }

    public function configureDashboard(): \EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard
    {
        return \EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard::new()
            ->setTitle('Ganesha · админка');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Главная', 'fa fa-home');
        yield MenuItem::linkToRoute('Меню недели', 'fa fa-calendar', 'admin_menu_week');
        yield MenuItem::linkToRoute('Кухня', 'fa fa-utensils', 'admin_kitchen');
        yield MenuItem::section('Справочники');
        yield MenuItem::linkToCrud('Блюда', 'fa fa-bowl-food', Dish::class);
        yield MenuItem::linkToCrud('Дни меню', 'fa fa-calendar-day', MenuDay::class);
        yield MenuItem::linkToCrud('Точки выдачи', 'fa fa-location-dot', PickupPoint::class);
        yield MenuItem::section('Заказы');
        yield MenuItem::linkToCrud('Заказы', 'fa fa-receipt', Order::class);
        yield MenuItem::linkToCrud('Клиенты', 'fa fa-user', Customer::class);
        yield MenuItem::section('Система');
        yield MenuItem::linkToCrud('Админы', 'fa fa-shield', AdminUser::class);
    }
}
