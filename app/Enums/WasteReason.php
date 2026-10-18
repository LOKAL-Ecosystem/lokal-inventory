<?php

namespace App\Enums;

enum WasteReason: string
{
    case Spilled = 'spilled';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case WrongOrder = 'wrong_order';

    public function label(): string
    {
        return match ($this) {
            self::Spilled => 'Tumpah',
            self::Damaged => 'Rusak',
            self::Expired => 'Kedaluwarsa',
            self::WrongOrder => 'Salah buat',
        };
    }
}
