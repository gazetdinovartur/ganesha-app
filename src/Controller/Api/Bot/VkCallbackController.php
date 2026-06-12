<?php

namespace App\Controller\Api\Bot;

use App\Service\Bot\BotOrderFlowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VkCallbackController extends AbstractController
{
    public function __construct(
        private readonly BotOrderFlowService $botOrderFlowService,
    ) {
    }

    #[Route('/api/bot/vk/callback', name: 'api_bot_vk_callback', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new Response('invalid', Response::HTTP_BAD_REQUEST);
        }

        $response = $this->botOrderFlowService->handleVkEvent($payload);

        return new Response($response);
    }
}
