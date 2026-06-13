<?php

namespace App\Enum;

enum OrderChannel: string
{
    case Web = 'web';
    case Telegram = 'telegram';
    case Vk = 'vk';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Сайт',
            self::Telegram => 'Telegram',
            self::Vk => 'VK',
        };
    }
}
