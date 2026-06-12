<?php

namespace App\Dto;

use App\Enum\OrderChannel;

final readonly class CreateOrderDto
{
    /**
     * @param list<CreateOrderItemDto> $items
     */
    public function __construct(
        public string $phone,
        public \DateTimeImmutable $pickupDate,
        public array $items,
        public string $name = '',
        public ?int $pickupPointId = null,
        public OrderChannel $channel = OrderChannel::Web,
        public ?string $comment = null,
        public bool $personalDataConsent = false,
    ) {
    }
}
