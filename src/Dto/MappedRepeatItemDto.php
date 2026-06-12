<?php

namespace App\Dto;

final readonly class MappedRepeatItemDto
{
    public function __construct(
        public int $menuDayDishId,
        public int $quantity,
        public string $name,
        public int $unitPrice,
    ) {
    }
}
