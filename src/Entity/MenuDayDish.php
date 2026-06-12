<?php

namespace App\Entity;

use App\Repository\MenuDayDishRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuDayDishRepository::class)]
#[ORM\Table(name: 'menu_day_dish')]
#[ORM\UniqueConstraint(name: 'uniq_menu_day_dish', columns: ['menu_day_id', 'dish_id'])]
class MenuDayDish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dishes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MenuDay $menuDay = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dish $dish = null;

    /** @var int|null Price override in kopecks */
    #[ORM\Column(nullable: true)]
    private ?int $priceOverride = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column]
    private int $orderedPortions = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenuDay(): ?MenuDay
    {
        return $this->menuDay;
    }

    public function setMenuDay(?MenuDay $menuDay): static
    {
        $this->menuDay = $menuDay;

        return $this;
    }

    public function getDish(): ?Dish
    {
        return $this->dish;
    }

    public function setDish(?Dish $dish): static
    {
        $this->dish = $dish;

        return $this;
    }

    public function getPriceOverride(): ?int
    {
        return $this->priceOverride;
    }

    public function setPriceOverride(?int $priceOverride): static
    {
        $this->priceOverride = $priceOverride;

        return $this;
    }

    public function getEffectivePrice(): int
    {
        if ($this->priceOverride !== null) {
            return $this->priceOverride;
        }

        return $this->dish?->getPrice() ?? 0;
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

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getOrderedPortions(): int
    {
        return $this->orderedPortions;
    }

    public function setOrderedPortions(int $orderedPortions): static
    {
        $this->orderedPortions = $orderedPortions;

        return $this;
    }

    public function incrementOrderedPortions(int $quantity): static
    {
        $this->orderedPortions += $quantity;

        return $this;
    }
}
