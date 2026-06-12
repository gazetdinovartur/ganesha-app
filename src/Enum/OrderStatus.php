<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Ожидает оплаты',
            self::Paid => 'Оплачен',
            self::Ready => 'Можно забирать',
            self::Completed => 'Выполнен',
            self::Cancelled => 'Отменён',
        };
    }
}
