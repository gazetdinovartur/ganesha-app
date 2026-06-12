<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Dish;
use App\Repository\DishCategoryRepository;
use App\EventListener\DishCrudFormListener;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** @extends AbstractCrudController<Dish> */
final class DishCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly DishCrudFormListener $dishCrudFormListener,
    ) {
    }
    public static function getEntityFqcn(): string
    {
        return Dish::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Блюдо')
            ->setEntityLabelInPlural('Блюда')
            ->setPageTitle(Crud::PAGE_INDEX, 'Блюда')
            ->setPageTitle(Crud::PAGE_NEW, 'Новое блюдо')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Dish $dish): string => sprintf('Редактирование: %s', $dish->getName() ?: 'блюдо'))
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Dish $dish): string => sprintf('Блюдо: %s', $dish->getName() ?: '—'))
            ->setDefaultSort(['sortOrder' => 'ASC', 'name' => 'ASC'])
            ->showEntityActionsInlined()
            ->setFormThemes([
                'form/dish_form_theme.html.twig',
                'form/admin_theme.html.twig',
                '@EasyAdmin/crud/form_theme.html.twig',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('category', 'Категория'));
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $builder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        $builder->addEventSubscriber($this->dishCrudFormListener);

        return $builder;
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $builder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        $builder->addEventSubscriber($this->dishCrudFormListener);

        return $builder;
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('name', 'Название');
            yield AssociationField::new('category', 'Категория');
            yield IntegerField::new('price', 'Цена, руб')
                ->formatValue(fn (?int $value): string => $value === null ? '—' : number_format($value / 100, 2, ',', ' ').' ₽');
            yield BooleanField::new('isActive', 'Активно');

            return;
        }

        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('name', 'Название');
            yield AssociationField::new('category', 'Категория');
            yield IntegerField::new('price', 'Цена, руб')
                ->formatValue(fn (?int $value): string => $value === null ? '—' : number_format($value / 100, 2, ',', ' ').' ₽');
            yield TextField::new('shortDescription', 'Описание');
            yield TextField::new('photoPath', 'Фото');
            yield BooleanField::new('isActive', 'Активно');

            return;
        }

        yield TextField::new('name', 'Название')
            ->setFormTypeOption('attr', ['placeholder' => 'Например: Суп из чечевицы'])
            ->setColumns(12);
        yield AssociationField::new('category', 'Категория')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', 'Выберите категорию')
            ->setFormTypeOption('query_builder', fn (DishCategoryRepository $repo) => $repo->createQueryBuilder('c')
                ->orderBy('c.sortOrder', 'ASC')
                ->addOrderBy('c.name', 'ASC'))
            ->setColumns(12);
        yield TextField::new('newCategoryName', 'Новая категория')
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('help', 'Если заполнено, категория создастся автоматически (например: салаты, супы, второе, выпечка, десерты, напитки).')
            ->setFormTypeOption('attr', ['placeholder' => 'Введите название новой категории'])
            ->setColumns(12);
        yield TextareaField::new('shortDescription', 'Краткое описание')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('attr', ['rows' => 2, 'placeholder' => 'Для клиента на сайте'])
            ->setColumns(12);
        yield NumberField::new('priceRub', 'Цена, руб')
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('html5', true)
            ->setFormTypeOption('scale', 2)
            ->setFormTypeOption('attr', ['placeholder' => '350.00', 'step' => '0.01', 'min' => '0'])
            ->setColumns(12);
        yield TextField::new('photoPath', 'Путь к фото')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('attr', ['placeholder' => '/uploads/dishes/soup.jpg'])
            ->setColumns(12);
        yield BooleanField::new('isActive', 'Активно')->setColumns(12);

        yield IntegerField::new('weightG', 'Вес порции, г')
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('attr', ['placeholder' => '350', 'min' => 0])
            ->setColumns(12);
        yield CollectionField::new('ingredients', 'Ингредиенты (компоненты состава)')
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->setEntryType(TextType::class)
            ->setFormTypeOption('entry_options', [
                'label' => false,
                'attr' => ['placeholder' => 'Например: чечевица'],
            ])
            ->allowAdd()
            ->allowDelete()
            ->setColumns(12);
        yield TextField::new('allergensText', 'Аллергены')
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('help', 'Через запятую, если есть')
            ->setFormTypeOption('attr', ['placeholder' => 'орехи, глютен'])
            ->setColumns(12);
        yield TextareaField::new('compositionNote', 'Примечание к составу')
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('attr', ['rows' => 2, 'placeholder' => 'лёгкий, без острого'])
            ->setColumns(12);
    }
}
