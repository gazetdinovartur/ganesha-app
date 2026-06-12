<?php

namespace App\Service\Bot;

use App\Entity\BotSession;
use App\Enum\BotPlatform;
use App\Repository\BotSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BotSessionService
{
    public function __construct(
        private readonly BotSessionRepository $botSessionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreate(BotPlatform $platform, string $externalUserId): BotSession
    {
        $session = $this->botSessionRepository->findOneByUser($platform, $externalUserId);
        if ($session instanceof BotSession) {
            return $session;
        }

        $session = new BotSession($platform, $externalUserId);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    public function reset(BotSession $session): BotSession
    {
        return $session
            ->setState('start')
            ->setPayload([]);
    }

    /**
     * @return array<int, int> menuDayDishId => quantity
     */
    public function getCart(BotSession $session): array
    {
        $cart = $session->getPayload()['cart'] ?? [];

        return \is_array($cart) ? array_map('intval', $cart) : [];
    }

    public function setCart(BotSession $session, array $cart): BotSession
    {
        return $session->mergePayload(['cart' => $cart]);
    }
}
