<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\PosProduct;
use App\Models\RecipeItem;

/**
 * HPP menu = Σ (takaran bahan resep dasar × avg_cost). Dihitung langsung dari avg_cost
 * terbaru, jadi otomatis ikut berubah saat ada stok masuk atau resep berubah.
 * Pembulatan ke rupiah hanya pada hasil tampilan.
 */
class CostingService
{
    public function __construct(protected RecipeUsageCalculator $usage)
    {
    }

    public function recipeCost(int $posProductId): float
    {
        return RecipeItem::with('ingredient')
            ->where('pos_product_id', $posProductId)
            ->get()
            ->sum(fn (RecipeItem $item) => (float) $item->qty * (float) ($item->ingredient?->avg_cost ?? 0));
    }

    /**
     * HPP untuk kombinasi kustomisasi tertentu (lanjutan).
     *
     * @param  array<int>  $modifierIds
     */
    public function costForSelection(int $posProductId, array $modifierIds): float
    {
        $usage = $this->usage->usage($posProductId, $modifierIds, 1);
        $ingredients = Ingredient::whereIn('id', array_keys($usage))->get()->keyBy('id');

        $total = 0.0;
        foreach ($usage as $ingredientId => $qty) {
            $total += $qty * (float) ($ingredients[$ingredientId]->avg_cost ?? 0);
        }

        return $total;
    }

    /**
     * @return array{hpp: int, margin: int, margin_pct: float|null, has_recipe: bool}
     */
    public function summary(PosProduct $product): array
    {
        $hasRecipe = RecipeItem::where('pos_product_id', $product->pos_product_id)->exists();
        $hpp = (int) round($this->recipeCost($product->pos_product_id));
        $margin = (int) $product->price - $hpp;

        return [
            'hpp' => $hpp,
            'margin' => $margin,
            'margin_pct' => $product->price > 0 ? round($margin / $product->price * 100, 1) : null,
            'has_recipe' => $hasRecipe,
        ];
    }

    /**
     * Nilai stok = Σ stok positif × avg_cost, dibulatkan ke rupiah.
     */
    public function stockValue(): int
    {
        return (int) round(
            Ingredient::query()->where('current_stock', '>', 0)->get()
                ->sum(fn (Ingredient $i) => (float) $i->current_stock * (float) $i->avg_cost)
        );
    }
}
