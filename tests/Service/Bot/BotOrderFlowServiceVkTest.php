<?php

namespace App\Tests\Service\Bot;

use App\Entity\BotSession;
use App\Enum\BotPlatform;
use App\Repository\BotSessionRepository;
use App\Service\Bot\BotOrderFlowService;
use App\Service\Bot\BotSessionService;
use App\Service\CustomerService;
use App\Service\MenuCatalogService;
use App\Service\Notification\TelegramApiClient;
use App\Service\Notification\VkApiClient;
use App\Service\OrderApiPresenter;
use App\Service\OrderRepeatService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BotOrderFlowServiceVkTest extends TestCase
{
    public function testVkDateSelectionShowsDishes(): void
    {
        $session = new BotSession(BotPlatform::Vk, '200');
        $botSessionService = new BotSessionService(
            $this->createConfiguredMock(BotSessionRepository::class, [
                'findOneByUser' => $session,
            ]),
            $this->createMock(EntityManagerInterface::class),
        );

        $vkApiClient = $this->createMock(VkApiClient::class);
        $vkApiClient->method('isConfigured')->willReturn(true);
        $vkApiClient
            ->expects(self::once())
            ->method('sendMessage')
            ->with(
                200,
                self::callback(static fn (string $message): bool => str_contains($message, 'Меню на 2026-06-14')
                    && str_contains($message, '1. Суп')),
            );

        $menuCatalogService = $this->createMock(MenuCatalogService::class);
        $menuCatalogService->method('getPublishedMenu')->willReturn([
            [
                'date' => '2026-06-14',
                'dishes' => [
                    ['menu_day_dish_id' => 3, 'name' => 'Суп', 'price' => 30000],
                ],
            ],
        ]);

        $flow = new BotOrderFlowService(
            $botSessionService,
            $this->createMock(CustomerService::class),
            $menuCatalogService,
            $this->createMock(OrderService::class),
            $this->createMock(OrderRepeatService::class),
            new OrderApiPresenter('', '', ''),
            $this->createMock(TelegramApiClient::class),
            $vkApiClient,
            $this->createMock(EntityManagerInterface::class),
            '',
            'vk-secret',
        );

        self::assertSame('ok', $flow->handleVkEvent([
            'type' => 'message_new',
            'object' => [
                'message' => [
                    'from_id' => 200,
                    'text' => '2026-06-14',
                ],
            ],
        ]));

        self::assertSame('select_dish', $session->getState());
        self::assertSame('2026-06-14', $session->getPayload()['pickup_date'] ?? null);
    }
}
