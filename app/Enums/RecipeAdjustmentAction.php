<?php

namespace App\Enums;

enum RecipeAdjustmentAction: string
{
    case Add = 'add';
    case Replace = 'replace';
    case Multiply = 'multiply';

    public function label(): string
    {
        return match ($this) {
            self::Add => 'Tambah bahan',
            self::Replace => 'Ganti bahan',
            self::Multiply => 'Kalikan takaran',
        };
    }
}
