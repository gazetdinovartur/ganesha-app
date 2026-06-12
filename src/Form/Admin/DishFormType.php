<?php

namespace App\Form\Admin;

use App\Entity\Dish;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DishFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Название'])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'Краткое описание',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('priceRub', NumberType::class, [
                'label' => 'Цена, ₽',
                'mapped' => false,
                'html5' => true,
                'scale' => 2,
            ])
            ->add('weightG', IntegerType::class, [
                'label' => 'Вес порции, г',
                'mapped' => false,
                'required' => false,
            ])
            ->add('ingredientsText', TextareaType::class, [
                'label' => 'Ингредиенты',
                'mapped' => false,
                'required' => false,
                'help' => 'По одному на строку',
                'attr' => ['rows' => 4],
            ])
            ->add('allergensText', TextType::class, [
                'label' => 'Аллергены',
                'mapped' => false,
                'required' => false,
                'help' => 'Через запятую',
            ])
            ->add('compositionNote', TextareaType::class, [
                'label' => 'Примечание к составу',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('photoPath', TextType::class, [
                'label' => 'Путь к фото',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, ['label' => 'Активно', 'required' => false])
            ->add('sortOrder', IntegerType::class, ['label' => 'Порядок']);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $dish = $event->getData();
            if (!$dish instanceof Dish) {
                return;
            }

            $form = $event->getForm();
            $composition = $dish->getComposition();

            $form->get('priceRub')->setData($dish->getPrice() / 100);
            $form->get('weightG')->setData($composition['weight_g'] ?? null);
            $form->get('ingredientsText')->setData(implode("\n", $composition['ingredients'] ?? []));
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
                preg_split('/\R/u', (string) $form->get('ingredientsText')->getData()) ?: []
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
