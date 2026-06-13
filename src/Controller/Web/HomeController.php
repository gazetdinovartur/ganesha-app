<?php

namespace App\Controller\Web;

use App\Service\WebMenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly WebMenuService $webMenuService,
    ) {
    }

    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function index(): Response
    {
        $menu = $this->webMenuService->buildMenuPage();

        return $this->render('web/home/index.html.twig', [
            'menu' => $menu,
        ]);
    }
}
