<?php

namespace App\Controller\Web;

use App\Service\OrderRepeatService;
use App\Service\WebMenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RepeatOrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepeatService $orderRepeatService,
        private readonly WebMenuService $webMenuService,
    ) {
    }

    #[Route('/order/repeat/{token}', name: 'web_order_repeat', methods: ['GET'])]
    public function index(string $token): Response
    {
        $sourceOrder = $this->orderRepeatService->getSourceOrder($token);
        if ($sourceOrder === null) {
            throw $this->createNotFoundException('Ссылка повтора недействительна.');
        }

        return $this->render('web/order/repeat.html.twig', [
            'preview' => $this->orderRepeatService->buildPreview($sourceOrder),
            'menu' => $this->webMenuService->buildMenuPage(),
            'repeat_token' => $token,
        ]);
    }
}
