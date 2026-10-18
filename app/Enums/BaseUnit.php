<?php

namespace App\Enums;

enum BaseUnit: string
{
    case Gram = 'g';
    case Milliliter = 'ml';
    case Piece = 'pcs';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'Gram (g)',
            self::Milliliter => 'Mililiter (ml)',
            self::Piece => 'Buah (pcs)',
        };
    }
}
