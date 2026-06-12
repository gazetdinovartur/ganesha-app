<?php

namespace App\Controller\Admin;

use App\Repository\DishRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/dishes/sort', name: 'dishes_sort', options: ['methods' => ['POST']])]
final class DishSortController extends AbstractController
{
    public function __construct(
        private readonly DishRepository $dishRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload) || !isset($payload['ids']) || !\is_array($payload['ids'])) {
            return $this->json(['ok' => false, 'message' => 'Некорректные данные.'], 400);
        }

        $ids = array_values(array_unique(array_map('intval', $payload['ids'])));
        if ($ids === []) {
            return $this->json(['ok' => true]);
        }

        $dishes = $this->dishRepository->createQueryBuilder('d')
            ->andWhere('d.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $dishesById = [];
        foreach ($dishes as $dish) {
            $dishesById[$dish->getId()] = $dish;
        }

        $position = 1;
        foreach ($ids as $id) {
            if (!isset($dishesById[$id])) {
                continue;
            }
            $dishesById[$id]->setSortOrder($position);
            ++$position;
        }

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
