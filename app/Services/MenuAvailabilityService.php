<?php

namespace App\Services;

use App\Jobs\PushAvailabilityToPos;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use Illuminate\Support\Collection;

/**
 * Menu dianggap habis bila salah satu bahan resep dasarnya tidak cukup untuk satu porsi.
 * Perubahan (habis <-> tersedia) hanya dikirim ke POS saat statusnya berubah, dan tanda
 * habis manual dari pemilik di POS tidak pernah ditimpa (lihat auto_unavailable).
 */
class MenuAvailabilityService
{
    /**
     * @param  array<int>  $ingredientIds
     * @return int jumlah produk yang statusnya berubah
     */
    public function refreshForIngredients(array $ingredientIds): int
    {
        if ($ingredientIds === []) {
            return 0;
        }

        $productIds = RecipeItem::query()
            ->whereIn('ingredient_id', $ingredientIds)
            ->pluck('pos_product_id')
            ->unique();

        return $this->refreshProducts($productIds);
    }

    public function refreshAll(): int
    {
        return $this->refreshProducts(RecipeItem::query()->pluck('pos_product_id')->unique());
    }

    /**
     * @param  Collection<int, int>  $posProductIds
     */
    public function refreshProducts(Collection $posProductIds): int
    {
        $changed = 0;

        PosProduct::query()
            ->whereIn('pos_product_id', $posProductIds)
            ->get()
            ->each(function (PosProduct $product) use (&$changed) {
                if ($this->refreshProduct($product)) {
                    $changed++;
                }
            });

        return $changed;
    }

    public function refreshProduct(PosProduct $product): bool
    {
        $items = RecipeItem::with('ingredient')
            ->where('pos_product_id', $product->pos_product_id)
            ->get();

        if ($items->isEmpty()) {
            return false;
        }

        $sufficient = $items->every(fn (RecipeItem $item) => $item->ingredient
            && (float) $item->ingredient->current_stock >= (float) $item->qty);

        $changed = false;

        if (! $sufficient && $product->is_available) {
            // Habis karena stok. Bila sudah habis (manual di POS), tidak disentuh.
            $product->auto_unavailable = true;
            $product->is_available = false;
            $changed = true;
        } elseif ($sufficient && $product->auto_unavailable) {
            // Kembali tersedia, hanya untuk tanda habis yang kita pasang sendiri.
            $product->auto_unavailable = false;
            $product->is_available = true;
            $changed = true;
        }

        $product->stock_sufficient = $sufficient;

        if ($changed || $product->isDirty('stock_sufficient')) {
            $product->save();
        }

        if ($changed) {
            PushAvailabilityToPos::dispatch($product->outlet_id, $product->pos_product_id, $product->is_available);
        }

        return $changed;
    }

    /**
     * Berapa porsi yang masih bisa dibuat dari stok sekarang (resep dasar). Null bila tanpa resep.
     */
    public function portions(int $posProductId): ?int
    {
        $items = RecipeItem::with('ingredient')->where('pos_product_id', $posProductId)->get();

        if ($items->isEmpty()) {
            return null;
        }

        $portions = $items->map(function (RecipeItem $item) {
            $qty = (float) $item->qty;

            return $qty > 0 && $item->ingredient
                ? (int) floor(max(0, (float) $item->ingredient->current_stock) / $qty)
                : 0;
        })->min();

        return (int) $portions;
    }
}
