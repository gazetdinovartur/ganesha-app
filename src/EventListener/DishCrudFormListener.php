<?php

namespace App\EventListener;

use App\Entity\Dish;
use App\Entity\DishCategory;
use App\Repository\DishCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class DishCrudFormListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly DishCategoryRepository $dishCategoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SET_DATA => 'onPostSetData',
            FormEvents::SUBMIT => 'onSubmit',
        ];
    }

    public function onPostSetData(FormEvent $event): void
    {
        $dish = $event->getData();
        if (!$dish instanceof Dish) {
            return;
        }

        $form = $event->getForm();
        if (!$form->has('priceRub')) {
            return;
        }

        $composition = $dish->getComposition();

        $form->get('priceRub')->setData($dish->getPrice() / 100);
        $form->get('weightG')->setData($composition['weight_g'] ?? null);

        $ingredients = $composition['ingredients'] ?? [];
        $form->get('ingredients')->setData($ingredients !== [] ? $ingredients : ['']);

        $form->get('allergensText')->setData(implode(', ', $composition['allergens'] ?? []));
        $form->get('compositionNote')->setData($composition['note'] ?? null);
    }

    public function onSubmit(FormEvent $event): void
    {
        $dish = $event->getData();
        if (!$dish instanceof Dish) {
            return;
        }

        $form = $event->getForm();
        if (!$form->has('priceRub')) {
            return;
        }

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

        if (!$form->has('newCategoryName')) {
            return;
        }

        $newCategoryName = trim((string) $form->get('newCategoryName')->getData());
        if ($newCategoryName === '') {
            return;
        }

        $category = $this->dishCategoryRepository->findOneByNameInsensitive($newCategoryName);
        if (!$category instanceof DishCategory) {
            $category = (new DishCategory())
                ->setName($newCategoryName)
                ->setSortOrder($this->dishCategoryRepository->nextSortOrder());
            $this->entityManager->persist($category);
        }

        $dish->setCategory($category);
    }
}
