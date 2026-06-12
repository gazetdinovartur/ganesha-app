<?php

namespace App\Form\Admin;

use App\Entity\Dish;
use App\Entity\MenuDayDish;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class MenuDayDishFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dish', EntityType::class, [
                'label' => 'Блюдо',
                'class' => Dish::class,
                'choice_label' => 'name',
                'placeholder' => 'Выберите блюдо',
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank(message: 'Выберите блюдо.')],
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('d')
                    ->andWhere('d.isActive = true')
                    ->orderBy('d.sortOrder', 'ASC'),
            ])
            ->add('priceOverrideRub', NumberType::class, [
                'label' => 'Цена дня, ₽',
                'mapped' => false,
                'required' => false,
                'html5' => true,
                'scale' => 2,
                'attr' => ['placeholder' => 'базовая', 'step' => '0.01', 'min' => '0'],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Порядок',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'class' => 'form-control text-center'],
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $menuDayDish = $event->getData();
            if (!$menuDayDish instanceof MenuDayDish) {
                return;
            }

            $override = $menuDayDish->getPriceOverride();
            if ($override !== null) {
                $event->getForm()->get('priceOverrideRub')->setData($override / 100);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $menuDayDish = $event->getData();
            if (!$menuDayDish instanceof MenuDayDish) {
                return;
            }

            $rub = $event->getForm()->get('priceOverrideRub')->getData();
            $menuDayDish->setPriceOverride($rub === null || $rub === '' ? null : (int) round((float) $rub * 100));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MenuDayDish::class,
        ]);
    }
}
