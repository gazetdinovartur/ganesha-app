<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Crud\AdminUserCrudController;
use App\Controller\Admin\Crud\CustomerCrudController;
use App\Controller\Admin\Crud\DishCrudController;
use App\Controller\Admin\Crud\MenuDayCrudController;
use App\Controller\Admin\Crud\OrderCrudController;
use App\Controller\Admin\Crud\PickupPointCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
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
            ->setTitle('Ganesha · админка')
            ->setFaviconPath('images/seo/favicon-32x32.png');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('css/admin-custom.css')
            ->addJsFile('js/admin-forms.js');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Главная', 'fa fa-home');
        yield MenuItem::linkTo(MenuWeekController::class, 'Меню недели', 'fa fa-calendar');
        yield MenuItem::linkTo(KitchenController::class, 'Кухня', 'fa fa-utensils');
        yield MenuItem::section('Справочники');
        yield MenuItem::linkTo(DishCrudController::class, 'Блюда', 'fa fa-bowl-food');
        yield MenuItem::linkTo(MenuDayCrudController::class, 'Дни меню', 'fa fa-calendar-day');
        yield MenuItem::linkTo(PickupPointCrudController::class, 'Точки выдачи', 'fa fa-location-dot');
        yield MenuItem::section('Заказы');
        yield MenuItem::linkTo(OrderCrudController::class, 'Заказы', 'fa fa-receipt');
        yield MenuItem::linkTo(CustomerCrudController::class, 'Клиенты', 'fa fa-user');
        yield MenuItem::section('Система');
        yield MenuItem::linkTo(AdminUserCrudController::class, 'Админы', 'fa fa-shield');
    }
}
