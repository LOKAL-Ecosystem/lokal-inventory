<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\RecipeModifierAdjustment;
use App\Services\RecipeCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RecipeCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RecipeCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RecipeCalculatorService();
    }

    protected function createItem(string $name, float $quantity = 0.0): Item
    {
        return Item::create([
            'sku' => 'SKU-' . uniqid(),
            'name' => $name,
            'quantity_on_hand' => $quantity,
            'minimum_stock' => 10.0,
            'unit_cost' => 1000.0,
            'is_active' => true,
        ]);
    }

    public function test_base_recipe_only_without_modifiers()
    {
        $item1 = $this->createItem('Susu Sapi');
        $item2 = $this->createItem('Espresso Shot');

        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu Medium',
            'stock_item_id' => $item1->id,
            'quantity_needed' => 0.150, // 150ml
        ]);

        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu Medium',
            'stock_item_id' => $item2->id,
            'quantity_needed' => 1.0, // 1 shot
        ]);

        // Order 2 items without modifiers
        $deductions = $this->calculator->calculateDeduction(10, 2, []);

        $this->assertEquals([
            $item1->id => 0.300,
            $item2->id => 2.0,
        ], $deductions);
    }

    public function test_base_recipe_with_single_override_modifier()
    {
        $item1 = $this->createItem('Susu Sapi');

        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu Medium',
            'stock_item_id' => $item1->id,
            'quantity_needed' => 0.150,
        ]);

        // Modifier Large: override milk to 0.250
        RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 1, // Large modifier
            'pos_modifier_name' => 'Size Large',
            'stock_item_id' => $item1->id,
            'adjustment_type' => 'override',
            'adjustment_qty' => 0.250,
        ]);

        // Order 1 Large Kopi Susu
        $deductions = $this->calculator->calculateDeduction(10, 1, [1]);

        $this->assertEquals([
            $item1->id => 0.250,
        ], $deductions);
    }

    public function test_base_recipe_with_add_and_subtract_modifiers()
    {
        $gula = $this->createItem('Gula Liquid');
        $susu = $this->createItem('Susu Sapi');

        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu Medium',
            'stock_item_id' => $gula->id,
            'quantity_needed' => 0.020, // 20g
        ]);
        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu Medium',
            'stock_item_id' => $susu->id,
            'quantity_needed' => 0.150,
        ]);

        // Add 10g gula
        RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 2,
            'pos_modifier_name' => 'Extra Sugar',
            'stock_item_id' => $gula->id,
            'adjustment_type' => 'add',
            'adjustment_qty' => 0.010,
        ]);

        // Subtract 50g gula (should floor at 0)
        RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 3,
            'pos_modifier_name' => 'Less Sugar (-50g)',
            'stock_item_id' => $gula->id,
            'adjustment_type' => 'subtract',
            'adjustment_qty' => 0.050,
        ]);

        // Order 1 item with Less Sugar (20g - 50g = -30g -> clamped to 0)
        $deductionsLess = $this->calculator->calculateDeduction(10, 1, [3]);
        $this->assertEquals([
            $susu->id => 0.150,
        ], $deductionsLess);

        // Order 1 item with Extra Sugar (20g + 10g = 30g)
        $deductionsExtra = $this->calculator->calculateDeduction(10, 1, [2]);
        $this->assertEquals([
            $gula->id => 0.030,
            $susu->id => 0.150,
        ], $deductionsExtra);
    }

    public function test_base_recipe_with_multiple_modifiers_simultaneously()
    {
        $espresso = $this->createItem('Espresso Shot');
        $susu = $this->createItem('Susu Sapi');

        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu Medium',
            'stock_item_id' => $espresso->id,
            'quantity_needed' => 1.0,
        ]);
        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu Medium',
            'stock_item_id' => $susu->id,
            'quantity_needed' => 0.150,
        ]);

        // Modifier Large: override milk to 0.250
        RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 1,
            'pos_modifier_name' => 'Size Large',
            'stock_item_id' => $susu->id,
            'adjustment_type' => 'override',
            'adjustment_qty' => 0.250,
        ]);

        // Modifier Extra Shot: add 1 shot
        RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 4,
            'pos_modifier_name' => 'Extra Shot',
            'stock_item_id' => $espresso->id,
            'adjustment_type' => 'add',
            'adjustment_qty' => 1.0,
        ]);

        // Order 2 items with Large AND Extra Shot
        $deductions = $this->calculator->calculateDeduction(10, 2, [1, 4]);

        $this->assertEquals([
            $espresso->id => 4.0, // (1 + 1) * 2 = 4
            $susu->id => 0.500,  // 0.250 * 2 = 0.5
        ], $deductions);
    }

    public function test_modifier_introducing_new_stock_item_not_in_base_recipe()
    {
        $espresso = $this->createItem('Espresso Shot');
        $syrupVanila = $this->createItem('Syrup Vanila');

        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Black',
            'stock_item_id' => $espresso->id,
            'quantity_needed' => 2.0,
        ]);

        // Add Vanila Syrup modifier (item not in base recipe)
        RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 5,
            'pos_modifier_name' => 'Add Vanila Syrup',
            'stock_item_id' => $syrupVanila->id,
            'adjustment_type' => 'add',
            'adjustment_qty' => 0.015, // 15ml
        ]);

        $deductions = $this->calculator->calculateDeduction(10, 1, [5]);

        $this->assertEquals([
            $espresso->id => 2.0,
            $syrupVanila->id => 0.015,
        ], $deductions);
    }

    public function test_multiple_override_adjustments_picks_latest_updated_at_and_logs_warning()
    {
        Log::spy();

        $susu = $this->createItem('Susu Sapi');

        Recipe::create([
            'pos_product_id' => 10,
            'pos_product_name' => 'Kopi Susu',
            'stock_item_id' => $susu->id,
            'quantity_needed' => 0.150,
        ]);

        // Older override adjustment
        $olderAdj = RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 1,
            'pos_modifier_name' => 'Modifier A',
            'stock_item_id' => $susu->id,
            'adjustment_type' => 'override',
            'adjustment_qty' => 0.200,
            'updated_at' => now()->subMinutes(10),
        ]);

        // Newer override adjustment
        $newerAdj = RecipeModifierAdjustment::create([
            'pos_product_id' => 10,
            'pos_modifier_id' => 2,
            'pos_modifier_name' => 'Modifier B',
            'stock_item_id' => $susu->id,
            'adjustment_type' => 'override',
            'adjustment_qty' => 0.300,
            'updated_at' => now(),
        ]);

        // Both modifier 1 and 2 selected
        $deductions = $this->calculator->calculateDeduction(10, 1, [1, 2]);

        // Should use newer override (0.300)
        $this->assertEquals([
            $susu->id => 0.300,
        ], $deductions);

        Log::shouldHaveReceived('warning');
    }
}
