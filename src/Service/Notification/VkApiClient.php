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
        if (!$this->isConfigured()) {
            return;
        }

        $this->httpClient->request('POST', 'https://api.vk.com/method/messages.send', [
            'query' => [
                'access_token' => $this->groupToken,
                'v' => '5.199',
                'user_id' => $userId,
                'random_id' => random_int(1, PHP_INT_MAX),
                'message' => $text,
            ],
        ]);
    }
}
