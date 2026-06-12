<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Dish $dish = null;

    #[ORM\Column]
    private int $quantity = 1;

    /**
     * @var array{
     *     dish_id?: int|null,
     *     name: string,
     *     unit_price: int
     * }
     */
    #[ORM\Column(type: Types::JSON)]
    private array $dishSnapshot = [
        'dish_id' => null,
        'name' => '',
        'unit_price' => 0,
    ];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return array{
     *     dish_id?: int|null,
     *     name: string,
     *     unit_price: int
     * }
     */
    public function getDishSnapshot(): array
    {
        return $this->dishSnapshot;
    }

    /**
     * @param array{
     *     dish_id?: int|null,
     *     name: string,
     *     unit_price: int
     * } $dishSnapshot
     */
    public function setDishSnapshot(array $dishSnapshot): static
    {
        $this->dishSnapshot = $dishSnapshot;

        return $this;
    }

    public function getLineTotal(): int
    {
        return $this->quantity * (int) ($this->dishSnapshot['unit_price'] ?? 0);
    }
}
