<?php

namespace App\Services;

use App\Enums\RecipeAdjustmentAction;
use App\Models\ModifierRecipeAdjustment;
use App\Models\RecipeItem;

/**
 * Pemakaian bahan = resep dasar + penyesuaian opsi kustomisasi, lalu × qty.
 * Urutan penerapan: replace -> add -> multiply, supaya hasilnya konsisten.
 */
class RecipeUsageCalculator
{
    /**
     * @param  array<int>  $modifierIds
     * @return array<int, float> ingredient_id => qty (satuan dasar)
     */
    public function usage(int $posProductId, array $modifierIds, float $quantity = 1): array
    {
        $usage = [];

        foreach (RecipeItem::where('pos_product_id', $posProductId)->get() as $item) {
            $usage[$item->ingredient_id] = (float) $item->qty;
        }

        $adjustments = $modifierIds === []
            ? collect()
            : ModifierRecipeAdjustment::whereIn('pos_modifier_id', $modifierIds)->get();

        foreach ($adjustments->where('action', RecipeAdjustmentAction::Replace) as $adj) {
            $replaced = $usage[$adj->replaces_ingredient_id] ?? null;
            $amount = $adj->qty !== null ? (float) $adj->qty : $replaced;

            if ($amount === null) {
                continue; // bahan yang diganti tidak ada di resep dan tidak ada takaran pengganti
            }

            unset($usage[$adj->replaces_ingredient_id]);
            $usage[$adj->ingredient_id] = ($usage[$adj->ingredient_id] ?? 0) + $amount;
        }

        foreach ($adjustments->where('action', RecipeAdjustmentAction::Add) as $adj) {
            $usage[$adj->ingredient_id] = ($usage[$adj->ingredient_id] ?? 0) + (float) $adj->qty;
        }

        foreach ($adjustments->where('action', RecipeAdjustmentAction::Multiply) as $adj) {
            $factor = (float) $adj->factor;

            if ($adj->ingredient_id === null) {
                foreach ($usage as $ingredientId => $qty) {
                    $usage[$ingredientId] = $qty * $factor;
                }
            } elseif (isset($usage[$adj->ingredient_id])) {
                $usage[$adj->ingredient_id] *= $factor;
            }
        }

        return collect($usage)
            ->map(fn (float $qty) => round($qty * $quantity, 3))
            ->filter(fn (float $qty) => $qty > 0)
            ->all();
    }
}
