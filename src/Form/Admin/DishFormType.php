<?php

namespace App\Form\Admin;

use App\Entity\Dish;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DishFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название',
                'attr' => ['placeholder' => 'Например: Суп из чечевицы'],
                'constraints' => [new NotBlank(message: 'Укажите название блюда.')],
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'Краткое описание',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Для клиента на сайте'],
            ])
            ->add('priceRub', NumberType::class, [
                'label' => 'Цена, ₽',
                'mapped' => false,
                'html5' => true,
                'scale' => 2,
                'attr' => ['placeholder' => '350.00', 'step' => '0.01', 'min' => '0'],
                'constraints' => [
                    new NotBlank(message: 'Укажите цену.'),
                    new GreaterThan(value: 0, message: 'Цена должна быть больше нуля.'),
                ],
            ])
            ->add('photoPath', TextType::class, [
                'label' => 'Путь к фото',
                'required' => false,
                'attr' => ['placeholder' => '/uploads/dishes/soup.jpg'],
            ])
            ->add('isActive', CheckboxType::class, ['label' => 'Активно', 'required' => false])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Порядок в списке',
                'attr' => ['min' => 0],
            ])
            ->add('weightG', IntegerType::class, [
                'label' => 'Вес порции, г',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => '350', 'min' => 0],
            ])
            ->add('ingredients', CollectionType::class, [
                'label' => 'Ингредиенты (компоненты состава)',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => ['placeholder' => 'Например: чечевица'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'mapped' => false,
                'required' => false,
                'help' => 'Каждый ингредиент — отдельная строка. Кнопка «Добавить ингредиент» внизу списка.',
            ])
            ->add('allergensText', TextType::class, [
                'label' => 'Аллергены',
                'mapped' => false,
                'required' => false,
                'help' => 'Через запятую, если есть',
                'attr' => ['placeholder' => 'орехи, глютен'],
            ])
            ->add('compositionNote', TextareaType::class, [
                'label' => 'Примечание к составу',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'лёгкий, без острого'],
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $dish = $event->getData();
            if (!$dish instanceof Dish) {
                return;
            }

            $form = $event->getForm();
            $composition = $dish->getComposition();

            $form->get('priceRub')->setData($dish->getPrice() / 100);
            $form->get('weightG')->setData($composition['weight_g'] ?? null);

            $ingredients = $composition['ingredients'] ?? [];
            $form->get('ingredients')->setData($ingredients !== [] ? $ingredients : ['']);

            $form->get('allergensText')->setData(implode(', ', $composition['allergens'] ?? []));
            $form->get('compositionNote')->setData($composition['note'] ?? null);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $dish = $event->getData();
            if (!$dish instanceof Dish) {
                return;
            }

            $form = $event->getForm();

            $priceRub = (float) $form->get('priceRub')->getData();
            $dish->setPrice((int) round($priceRub * 100));

            $ingredients = array_values(array_filter(array_map(
                trim(...),
                $form->get('ingredients')->getData() ?? []
            )));

            $allergens = array_values(array_filter(array_map(
                trim(...),
                explode(',', (string) $form->get('allergensText')->getData())
            )));

            $dish->setComposition([
                'weight_g' => $form->get('weightG')->getData(),
                'ingredients' => $ingredients,
                'allergens' => $allergens,
                'note' => $form->get('compositionNote')->getData(),
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dish::class,
        ]);
    }
}
