<?php

namespace App\Service\Notification;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TelegramApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(param: 'app.telegram_bot_token')]
        private readonly string $botToken,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->botToken !== '';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sendMessage(string $chatId, string $text, array $options = []): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);

        $this->request('sendMessage', $payload);
    }

    /**
     * @param array<int, array<string, mixed>> $inlineKeyboard
     */
    public function sendMessageWithInlineKeyboard(string $chatId, string $text, array $inlineKeyboard): void
    {
        $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(string $method, array $payload): void
    {
        $this->httpClient->request(
            'POST',
            sprintf('https://api.telegram.org/bot%s/%s', $this->botToken, $method),
            ['json' => $payload],
        );
    }
}
