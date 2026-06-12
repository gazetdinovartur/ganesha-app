<?php

namespace App\Entity;

use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\UniqueConstraint(name: 'uniq_order_uuid', columns: ['uuid'])]
#[ORM\UniqueConstraint(name: 'uniq_order_human_number', columns: ['human_number'])]
#[ORM\UniqueConstraint(name: 'uniq_order_repeat_token', columns: ['repeat_token'])]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid')]
    private Uuid $uuid;

    #[ORM\Column(name: 'human_number')]
    private int $humanNumber = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $pickupDate;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?PickupPoint $pickupPoint = null;

    #[ORM\Column(length: 16, enumType: OrderChannel::class)]
    private OrderChannel $channel = OrderChannel::Web;

    #[ORM\Column(length: 32, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PendingPayment;

    /** @var int Total in kopecks */
    #[ORM\Column]
    private int $totalAmount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 64)]
    private string $repeatToken = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paymentClaimedAt = null;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
        $this->repeatToken = bin2hex(random_bytes(16));
        $this->pickupDate = new \DateTimeImmutable('today');
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getHumanNumber(): int
    {
        return $this->humanNumber;
    }

    public function setHumanNumber(int $humanNumber): static
    {
        $this->humanNumber = $humanNumber;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getPickupDate(): \DateTimeImmutable
    {
        return $this->pickupDate;
    }

    public function setPickupDate(\DateTimeImmutable $pickupDate): static
    {
        $this->pickupDate = $pickupDate;

        return $this;
    }

    public function getPickupPoint(): ?PickupPoint
    {
        return $this->pickupPoint;
    }

    public function setPickupPoint(?PickupPoint $pickupPoint): static
    {
        $this->pickupPoint = $pickupPoint;

        return $this;
    }

    public function getChannel(): OrderChannel
    {
        return $this->channel;
    }

    public function setChannel(OrderChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getRepeatToken(): string
    {
        return $this->repeatToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getPaymentClaimedAt(): ?\DateTimeImmutable
    {
        return $this->paymentClaimedAt;
    }

    public function setPaymentClaimedAt(?\DateTimeImmutable $paymentClaimedAt): static
    {
        $this->paymentClaimedAt = $paymentClaimedAt;

        return $this;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getOrder() === $this) {
            $item->setOrder(null);
        }

        return $this;
    }

    public function recalculateTotal(): static
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getLineTotal();
        }
        $this->totalAmount = $total;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('#%d', $this->humanNumber);
    }
}
