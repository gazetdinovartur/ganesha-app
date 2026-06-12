<?php

namespace App\Controller\Api;

use App\Service\MenuCatalogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/menu')]
final class MenuController extends AbstractController
{
    public function __construct(
        private readonly MenuCatalogService $menuCatalogService,
    ) {
    }

    #[Route('', name: 'api_menu_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'days' => $this->menuCatalogService->getPublishedMenu(),
        ]);
    }
}
