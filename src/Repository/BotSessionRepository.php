<?php

namespace App\Repository;

use App\Entity\BotSession;
use App\Enum\BotPlatform;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BotSession> */
class BotSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BotSession::class);
    }

    public function findOneByUser(BotPlatform $platform, string $externalUserId): ?BotSession
    {
        return $this->findOneBy([
            'platform' => $platform,
            'externalUserId' => $externalUserId,
        ]);
    }
}
