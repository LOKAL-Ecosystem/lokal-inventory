<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case VoidReturn = 'void_return';
    case Waste = 'waste';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Stok Masuk',
            self::Sale => 'Penjualan',
            self::VoidReturn => 'Kembali (Void)',
            self::Waste => 'Terbuang',
            self::Adjustment => 'Penyesuaian Opname',
        };
    }
}
