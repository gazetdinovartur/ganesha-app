<?php

namespace App\Controller\Api\Bot;

use App\Service\Bot\BotOrderFlowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TelegramWebhookController extends AbstractController
{
    public function __construct(
        private readonly BotOrderFlowService $botOrderFlowService,
    ) {
    }

    #[Route('/api/bot/telegram/webhook', name: 'api_bot_telegram_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new Response('invalid', Response::HTTP_BAD_REQUEST);
        }

        $this->botOrderFlowService->handleTelegramUpdate($payload);

        return new Response('ok');
    }
}
