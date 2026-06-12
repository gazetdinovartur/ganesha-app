<?php

namespace App\Enum;

enum OrderChannel: string
{
    case Web = 'web';
    case Telegram = 'telegram';
    case Vk = 'vk';
}
