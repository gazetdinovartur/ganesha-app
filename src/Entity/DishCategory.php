<?php

namespace App\Entity;

use App\Repository\DishCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DishCategoryRepository::class)]
#[ORM\Table(name: 'dish_category')]
#[ORM\UniqueConstraint(name: 'uniq_dish_category_name', columns: ['name'])]
class DishCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column]
    private int $sortOrder = 0;

    /** @var Collection<int, Dish> */
    #[ORM\OneToMany(targetEntity: Dish::class, mappedBy: 'category')]
    private Collection $dishes;

    public function __construct()
    {
        $this->dishes = new ArrayCollection();
    }

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
        $this->name = trim($name);

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

    /** @return Collection<int, Dish> */
    public function getDishes(): Collection
    {
        return $this->dishes;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
