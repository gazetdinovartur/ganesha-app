<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'customer')]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $phone = '';

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::BIGINT, nullable: true, unique: true)]
    private ?string $telegramId = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true, unique: true)]
    private ?string $vkId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $defaultComment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $personalDataConsentAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTelegramId(): ?string
    {
        return $this->telegramId;
    }

    public function setTelegramId(?string $telegramId): static
    {
        $this->telegramId = $telegramId;

        return $this;
    }

    public function getVkId(): ?string
    {
        return $this->vkId;
    }

    public function setVkId(?string $vkId): static
    {
        $this->vkId = $vkId;

        return $this;
    }

    public function getDefaultComment(): ?string
    {
        return $this->defaultComment;
    }

    public function setDefaultComment(?string $defaultComment): static
    {
        $this->defaultComment = $defaultComment;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPersonalDataConsentAt(): ?\DateTimeImmutable
    {
        return $this->personalDataConsentAt;
    }

    public function setPersonalDataConsentAt(?\DateTimeImmutable $personalDataConsentAt): static
    {
        $this->personalDataConsentAt = $personalDataConsentAt;

        return $this;
    }

    public function hasPersonalDataConsent(): bool
    {
        return $this->personalDataConsentAt !== null;
    }

    public function grantPersonalDataConsent(): static
    {
        $this->personalDataConsentAt = new \DateTimeImmutable();

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : $this->phone;
    }
}
