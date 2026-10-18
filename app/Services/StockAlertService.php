<?php

namespace App\Services;

use App\Models\Ingredient;

/**
 * Daftar bahan hampir habis (stok ≤ min_stock) dalam bentuk payload PUT /stock-alerts (kontrak v2.1).
 * Selalu daftar lengkap, bukan selisih.
 */
class StockAlertService
{
    public const MAX_ALERTS = 500;

    /**
     * Harus dipanggil dalam konteks outlet (global scope BelongsToOutlet).
     *
     * @return array<int, array{key: string, name: string, current: float, min: float, unit: string}>
     */
    public function alerts(): array
    {
        return Ingredient::lowStock()
            ->orderBy('id')
            ->limit(self::MAX_ALERTS)
            ->get()
            ->map(fn (Ingredient $ingredient) => [
                'key' => "ingredient-{$ingredient->id}",
                'name' => mb_substr($ingredient->name, 0, 255),
                'current' => (float) $ingredient->current_stock,
                'min' => (float) $ingredient->min_stock,
                'unit' => $ingredient->base_unit->value,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $alerts
     */
    public function hash(array $alerts): string
    {
        return hash('sha256', json_encode($alerts));
    }
}
