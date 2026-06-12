<?php

namespace App\Dto;

use App\Enum\OrderChannel;

final readonly class RepeatOrderDto
{
    /**
     * @param list<CreateOrderItemDto>|null $items null = повторить состав как есть (с маппингом на текущее меню)
     */
    public function __construct(
        public \DateTimeImmutable $pickupDate,
        public ?array $items = null,
        public ?string $comment = null,
        public OrderChannel $channel = OrderChannel::Web,
    ) {
    }
}
