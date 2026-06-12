<?php

namespace App\Dto;

final readonly class CreateOrderItemDto
{
    public function __construct(
        public int $menuDayDishId,
        public int $quantity = 1,
    ) {
    }
}
