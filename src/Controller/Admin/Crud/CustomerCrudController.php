<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Customer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Customer> */
final class CustomerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Customer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Клиент')
            ->setEntityLabelInPlural('Клиенты')
            ->setPageTitle(Crud::PAGE_INDEX, 'Клиенты')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Customer $customer): string => sprintf('Клиент: %s', $customer->getName() ?: '—'))
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Имя');
        yield TextField::new('phone', 'Телефон');
        yield TextField::new('telegramId', 'ID в Telegram')->hideOnIndex();
        yield TextField::new('vkId', 'ID во ВКонтакте')->hideOnIndex();
        yield TextareaField::new('defaultComment', 'Заметка')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Создан')->hideOnForm();
    }
}
