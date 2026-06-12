<?php

namespace App\Service\Notification;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VkApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(param: 'app.vk_group_token')]
        private readonly string $groupToken,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->groupToken !== '';
    }

    public function sendMessage(int $userId, string $text): void
    {
        $this->sendMessageWithInlineKeyboard($userId, $text, []);
    }

    /**
     * @param list<list<array{label: string, payload: string, color?: string}>> $rows
     */
    public function sendMessageWithInlineKeyboard(int $userId, string $text, array $rows): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $query = [
            'access_token' => $this->groupToken,
            'v' => '5.199',
            'user_id' => $userId,
            'random_id' => random_int(1, PHP_INT_MAX),
            'message' => $text,
        ];

        if ($rows !== []) {
            $query['keyboard'] = json_encode([
                'inline' => true,
                'buttons' => $this->buildButtons($rows),
            ], JSON_THROW_ON_ERROR);
        }

        $this->httpClient->request('POST', 'https://api.vk.com/method/messages.send', [
            'query' => $query,
        ]);
    }

    public function sendMessageEventAnswer(string $eventId, int $userId, int $peerId): void
    {
        if (!$this->isConfigured() || $eventId === '') {
            return;
        }

        $this->httpClient->request('POST', 'https://api.vk.com/method/messages.sendMessageEventAnswer', [
            'query' => [
                'access_token' => $this->groupToken,
                'v' => '5.199',
                'event_id' => $eventId,
                'user_id' => $userId,
                'peer_id' => $peerId,
            ],
        ]);
    }

    /**
     * @param list<list<array{label: string, payload: string, color?: string}>> $rows
     *
     * @return list<list<array<string, mixed>>>
     */
    private function buildButtons(array $rows): array
    {
        $buttons = [];

        foreach ($rows as $row) {
            $buttonRow = [];
            foreach ($row as $button) {
                $buttonRow[] = [
                    'action' => [
                        'type' => 'callback',
                        'label' => mb_substr($button['label'], 0, 40),
                        'payload' => $button['payload'],
                    ],
                    'color' => $button['color'] ?? 'secondary',
                ];
            }

            $buttons[] = $buttonRow;
        }

        return $buttons;
    }
}
