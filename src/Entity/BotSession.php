<?php

namespace App\Entity;

use App\Enum\BotPlatform;
use App\Repository\BotSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BotSessionRepository::class)]
#[ORM\Table(name: 'bot_session')]
#[ORM\UniqueConstraint(name: 'uniq_bot_session_user', columns: ['platform', 'external_user_id'])]
class BotSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, enumType: BotPlatform::class)]
    private BotPlatform $platform;

    #[ORM\Column(length: 64)]
    private string $externalUserId = '';

    #[ORM\Column(length: 64)]
    private string $state = 'start';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(BotPlatform $platform, string $externalUserId)
    {
        $this->platform = $platform;
        $this->externalUserId = $externalUserId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlatform(): BotPlatform
    {
        return $this->platform;
    }

    public function getExternalUserId(): string
    {
        return $this->externalUserId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;
        $this->touch();

        return $this;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;
        $this->touch();

        return $this;
    }

    public function mergePayload(array $data): static
    {
        $this->payload = array_merge($this->payload, $data);
        $this->touch();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
