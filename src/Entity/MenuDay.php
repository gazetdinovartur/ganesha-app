<?php

namespace App\Entity;

use App\Repository\MenuDayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuDayRepository::class)]
#[ORM\Table(name: 'menu_day')]
#[ORM\UniqueConstraint(name: 'uniq_menu_day_date', columns: ['date'])]
class MenuDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /** @var Collection<int, MenuDayDish> */
    #[ORM\OneToMany(targetEntity: MenuDayDish::class, mappedBy: 'menuDay', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $dishes;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable('today');
        $this->dishes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    /** @return Collection<int, MenuDayDish> */
    public function getDishes(): Collection
    {
        return $this->dishes;
    }

    public function addDish(MenuDayDish $dish): static
    {
        if (!$this->dishes->contains($dish)) {
            $this->dishes->add($dish);
            $dish->setMenuDay($this);
        }

        return $this;
    }

    public function removeDish(MenuDayDish $dish): static
    {
        if ($this->dishes->removeElement($dish) && $dish->getMenuDay() === $this) {
            $dish->setMenuDay(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->date->format('d.m.Y');
    }
}
