<?php

namespace App\Tests\Service\Bot;

use App\Entity\BotSession;
use App\Enum\BotPlatform;
use App\Repository\BotSessionRepository;
use App\Service\Bot\BotSessionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BotSessionServiceTest extends TestCase
{
    public function testGetCartAndSetCart(): void
    {
        $session = new BotSession(BotPlatform::Telegram, '42');
        $service = $this->createService($session);

        self::assertSame([], $service->getCart($session));

        $service->setCart($session, [10 => 2, 11 => 1]);

        self::assertSame([10 => 2, 11 => 1], $service->getCart($session));
    }

    public function testResetClearsPayloadAndState(): void
    {
        $session = new BotSession(BotPlatform::Vk, '99');
        $session->mergePayload(['pickup_date' => '2026-06-14', 'cart' => [1 => 1]])->setState('select_dish');
        $service = $this->createService($session);

        $service->reset($session);

        self::assertSame('start', $session->getState());
        self::assertSame([], $session->getPayload());
        self::assertSame([], $service->getCart($session));
    }

    private function createService(BotSession $session): BotSessionService
    {
        $repository = $this->createMock(BotSessionRepository::class);
        $repository->method('findOneByUser')->willReturn($session);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        return new BotSessionService($repository, $entityManager);
    }
}
