<?php

namespace App\Dto;

use App\Enum\OrderChannel;

final readonly class RepeatOrderDto
{
    /**
     * @param list<CreateOrderItemDto> $items
     */
    public function __construct(
        public \DateTimeImmutable $pickupDate,
        public array $items,
        public ?string $comment = null,
        public OrderChannel $channel = OrderChannel::Web,
    ) {
    }
}
