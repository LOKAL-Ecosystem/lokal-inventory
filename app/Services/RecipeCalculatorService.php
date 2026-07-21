<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeModifierAdjustment;
use Illuminate\Support\Facades\Log;

class RecipeCalculatorService
{
    /**
     * Calculate final stock ingredient deductions for a product transaction.
     *
     * @param int|string $posProductId ID of product in POS
     * @param int|float $qtyOrder Quantity of product ordered
     * @param array $selectedModifierIds Array of modifier IDs chosen by customer (e.g. [1, 5])
     * @return array Map of [stock_item_id => final_quantity_to_deduct]
     */
    public function calculateDeduction(int|string $posProductId, int|float $qtyOrder, array $selectedModifierIds = []): array
    {
        // 1. Fetch base recipe items for this POS product
        $baseRecipes = Recipe::where('pos_product_id', $posProductId)->get();
        
        $itemQuantities = [];
        foreach ($baseRecipes as $recipe) {
            $itemQuantities[(int) $recipe->stock_item_id] = (float) $recipe->quantity_needed;
        }

        // Sanitize modifier IDs array (ensure integers)
        $cleanModifierIds = array_values(array_filter(array_map('intval', $selectedModifierIds)));

        // If no modifiers selected, multiply base quantities directly by order quantity
        if (empty($cleanModifierIds)) {
            $result = [];
            foreach ($itemQuantities as $stockItemId => $baseQty) {
                $result[$stockItemId] = $baseQty * $qtyOrder;
            }
            return $result;
        }

        // 2. Fetch matching modifier adjustments
        $adjustments = RecipeModifierAdjustment::forPosProduct($posProductId)
            ->whereIn('pos_modifier_id', $cleanModifierIds)
            ->get();

        if ($adjustments->isEmpty()) {
            $result = [];
            foreach ($itemQuantities as $stockItemId => $baseQty) {
                $result[$stockItemId] = $baseQty * $qtyOrder;
            }
            return $result;
        }

        // 3. Separate adjustments into override, add, and subtract groups
        $overrides = [];
        $adds = [];
        $subtracts = [];

        foreach ($adjustments as $adj) {
            $stockItemId = (int) $adj->stock_item_id;
            $type = strtolower($adj->adjustment_type);

            if ($type === 'override') {
                if (!isset($overrides[$stockItemId])) {
                    $overrides[$stockItemId] = [];
                }
                $overrides[$stockItemId][] = $adj;
            } elseif ($type === 'add') {
                if (!isset($adds[$stockItemId])) {
                    $adds[$stockItemId] = 0.0;
                }
                $adds[$stockItemId] += (float) $adj->adjustment_qty;
            } elseif ($type === 'subtract') {
                if (!isset($subtracts[$stockItemId])) {
                    $subtracts[$stockItemId] = 0.0;
                }
                $subtracts[$stockItemId] += (float) $adj->adjustment_qty;
            }
        }

        // 4a. Process 'override' adjustments (select newest updated_at if duplicates exist)
        foreach ($overrides as $stockItemId => $adjList) {
            if (count($adjList) > 1) {
                // Sort by updated_at descending, then id descending
                usort($adjList, function ($a, $b) {
                    $timeA = $a->updated_at ? $a->updated_at->timestamp : 0;
                    $timeB = $b->updated_at ? $b->updated_at->timestamp : 0;
                    if ($timeA === $timeB) {
                        return $b->id <=> $a->id;
                    }
                    return $timeB <=> $timeA;
                });

                Log::warning("Multiple override adjustments detected for POS product {$posProductId} & stock_item {$stockItemId}. Using latest adjustment ID: {$adjList[0]->id}");
            }

            $chosenOverride = $adjList[0];
            $itemQuantities[$stockItemId] = (float) $chosenOverride->adjustment_qty;
        }

        // 4b. Process 'add' adjustments
        foreach ($adds as $stockItemId => $addQty) {
            $current = $itemQuantities[$stockItemId] ?? 0.0;
            $itemQuantities[$stockItemId] = $current + $addQty;
        }

        // 4c. Process 'subtract' adjustments (floor at 0.0)
        foreach ($subtracts as $stockItemId => $subQty) {
            $current = $itemQuantities[$stockItemId] ?? 0.0;
            $itemQuantities[$stockItemId] = max(0.0, $current - $subQty);
        }

        // 5. Multiply by order quantity
        $finalDeductions = [];
        foreach ($itemQuantities as $stockItemId => $perUnitQty) {
            $totalQty = $perUnitQty * $qtyOrder;
            if ($totalQty > 0) {
                $finalDeductions[$stockItemId] = $totalQty;
            }
        }

        return $finalDeductions;
    }
}
