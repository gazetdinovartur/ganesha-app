<?php

namespace App\Entity;

use App\Repository\DishRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DishRepository::class)]
#[ORM\Table(name: 'dish')]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shortDescription = null;

    /**
     * @var array{
     *     weight_g?: int|null,
     *     ingredients?: list<string>,
     *     allergens?: list<string>,
     *     note?: string|null
     * }
     */
    #[ORM\Column(type: Types::JSON)]
    private array $composition = [
        'weight_g' => null,
        'ingredients' => [],
        'allergens' => [],
        'note' => null,
    ];

    /** @var int Amount in kopecks */
    #[ORM\Column]
    private int $price = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoPath = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\ManyToOne(inversedBy: 'dishes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DishCategory $category = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    /**
     * @return array{
     *     weight_g?: int|null,
     *     ingredients?: list<string>,
     *     allergens?: list<string>,
     *     note?: string|null
     * }
     */
    public function getComposition(): array
    {
        return $this->composition;
    }

    /**
     * @param array{
     *     weight_g?: int|null,
     *     ingredients?: list<string>,
     *     allergens?: list<string>,
     *     note?: string|null
     * } $composition
     */
    public function setComposition(array $composition): static
    {
        $this->composition = $composition;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPhotoPath(): ?string
    {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $photoPath): static
    {
        $this->photoPath = $photoPath;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getCategory(): ?DishCategory
    {
        return $this->category;
    }

    public function setCategory(?DishCategory $category): static
    {
        $this->category = $category;

        return $this;
    }
}
