<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Expired   = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Очікує оплати',
            self::Paid      => 'Оплачено',
            self::Expired   => 'Прострочено',
            self::Cancelled => 'Скасовано',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending   => 'yellow',
            self::Paid      => 'green',
            self::Expired   => 'gray',
            self::Cancelled => 'red',
        };
    }
}
