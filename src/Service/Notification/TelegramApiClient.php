<?php

namespace App\Service\Notification;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramApiClient
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

    public function sendMessageWithContactRequest(string $chatId, string $text): void
    {
        $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'keyboard' => [[[
                    'text' => '📱 Отправить телефон',
                    'request_contact' => true,
                ]]],
                'one_time_keyboard' => true,
                'resize_keyboard' => true,
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    public function removeReplyKeyboard(string $chatId, string $text = ''): void
    {
        if ($text === '') {
            $this->request('sendMessage', [
                'chat_id' => $chatId,
                'text' => ' ',
                'reply_markup' => json_encode(['remove_keyboard' => true], JSON_THROW_ON_ERROR),
            ]);

            return;
        }

        $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode(['remove_keyboard' => true], JSON_THROW_ON_ERROR),
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        if (!$this->isConfigured() || $callbackQueryId === '') {
            return;
        }

        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null && $text !== '') {
            $payload['text'] = $text;
        }

        $this->request('answerCallbackQuery', $payload);
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
