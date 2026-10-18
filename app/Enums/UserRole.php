<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Cashier => 'Kasir',
        };
    }
}
