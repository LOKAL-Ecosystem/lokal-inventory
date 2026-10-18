<?php

namespace Database\Seeders;

use App\Enums\BaseUnit;
use App\Enums\RecipeAdjustmentAction;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\ModifierRecipeAdjustment;
use App\Models\Outlet;
use App\Models\PosModifier;
use App\Models\PosModifierGroup;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MenuAvailabilityService;
use App\Services\StockReceiptService;
use App\Support\CurrentOutlet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo: outlet "Lokal Coffee" dengan katalog yang sama seperti seeder lokal_pos
 * (urutan id produk mengikuti seeder POS; sinkron katalog akan menimpa dengan data asli).
 * Stok awal dibuat lewat StockReceiptService, bukan insert langsung.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = Outlet::firstOrCreate(['name' => 'Lokal Coffee'], [
            'pos_outlet_id' => 1,
            'pos_base_url' => 'http://127.0.0.1:8000',
        ]);

        foreach ([
            ['Owner Lokal', 'admin@lokal.com', UserRole::Owner],
            ['Kasir Lokal', 'kasir@lokal.com', UserRole::Cashier],
        ] as [$name, $email, $role]) {
            User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => $role,
                'outlet_id' => $outlet->id,
            ]);
        }

        app(CurrentOutlet::class)->run($outlet, function () {
            $this->seedCatalog();
            $ingredients = $this->seedIngredients();
            $this->seedRecipes($ingredients);
            $this->seedInitialStock($ingredients);
            app(MenuAvailabilityService::class)->refreshAll();
        });
    }

    protected function seedCatalog(): void
    {
        $products = [
            [1, 'Americano', 'Coffee', 20000],
            [2, 'Cafe Latte', 'Coffee', 25000],
            [3, 'Cappuccino', 'Coffee', 25000],
            [4, 'Kopi Susu Aren', 'Coffee', 22000],
        ];

        foreach ($products as [$id, $name, $category, $price]) {
            PosProduct::updateOrCreate(['pos_product_id' => $id], [
                'name' => $name, 'category' => $category, 'price' => $price, 'synced_at' => now(),
            ]);
        }

        $groups = [
            1 => ['Ukuran', [[1, 'Small', 0], [2, 'Regular', 0], [3, 'Large', 5000]]],
            2 => ['Susu', [[4, 'Susu Sapi', 0], [5, 'Oat Milk', 8000]]],
            3 => ['Tambahan', [[6, 'Extra Shot', 8000]]],
        ];

        foreach ($groups as $groupId => [$groupName, $modifiers]) {
            PosModifierGroup::updateOrCreate(['pos_group_id' => $groupId], ['name' => $groupName, 'synced_at' => now()]);

            foreach ($modifiers as [$modifierId, $modifierName, $delta]) {
                PosModifier::updateOrCreate(['pos_modifier_id' => $modifierId], [
                    'pos_group_id' => $groupId, 'name' => $modifierName, 'price_delta' => $delta, 'synced_at' => now(),
                ]);

                foreach ([1, 2, 3, 4] as $productId) {
                    DB::table('pos_product_modifiers')->insertOrIgnore([
                        'outlet_id' => app(CurrentOutlet::class)->id(),
                        'pos_product_id' => $productId,
                        'pos_modifier_id' => $modifierId,
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, Ingredient>
     */
    protected function seedIngredients(): array
    {
        $categories = collect(['Kopi', 'Susu & Dairy', 'Pemanis', 'Kemasan'])
            ->mapWithKeys(fn ($name) => [$name => IngredientCategory::firstOrCreate(['name' => $name])->id]);

        $rows = [
            // nama, kategori, satuan dasar, satuan beli, isi per satuan beli, stok minimum
            ['Biji Kopi', 'Kopi', BaseUnit::Gram, 'kg', 1000, 500],
            ['Susu Sapi', 'Susu & Dairy', BaseUnit::Milliliter, 'karton', 12000, 3000],
            ['Oat Milk', 'Susu & Dairy', BaseUnit::Milliliter, 'karton', 6000, 1000],
            ['Gula Aren Cair', 'Pemanis', BaseUnit::Milliliter, 'botol', 1000, 500],
            ['Cup 12oz', 'Kemasan', BaseUnit::Piece, 'sleeve', 50, 50],
            ['Tutup Cup', 'Kemasan', BaseUnit::Piece, 'sleeve', 50, 50],
        ];

        $result = [];
        foreach ($rows as [$name, $category, $unit, $purchaseUnit, $purchaseQty, $min]) {
            $result[$name] = Ingredient::firstOrCreate(['name' => $name], [
                'ingredient_category_id' => $categories[$category],
                'base_unit' => $unit,
                'purchase_unit' => $purchaseUnit,
                'purchase_unit_qty' => $purchaseQty,
                'min_stock' => $min,
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, Ingredient>  $i
     */
    protected function seedRecipes(array $i): void
    {
        $recipes = [
            1 => [['Biji Kopi', 18], ['Cup 12oz', 1], ['Tutup Cup', 1]],                                   // Americano
            2 => [['Biji Kopi', 18], ['Susu Sapi', 150], ['Cup 12oz', 1], ['Tutup Cup', 1]],               // Cafe Latte
            3 => [['Biji Kopi', 18], ['Susu Sapi', 120], ['Cup 12oz', 1], ['Tutup Cup', 1]],               // Cappuccino
            4 => [['Biji Kopi', 18], ['Susu Sapi', 100], ['Gula Aren Cair', 20], ['Cup 12oz', 1], ['Tutup Cup', 1]], // Kopi Susu Aren
        ];

        foreach ($recipes as $productId => $items) {
            foreach ($items as [$ingredient, $qty]) {
                RecipeItem::updateOrCreate(
                    ['pos_product_id' => $productId, 'ingredient_id' => $i[$ingredient]->id],
                    ['qty' => $qty]
                );
            }
        }

        if (ModifierRecipeAdjustment::count() === 0) {
            ModifierRecipeAdjustment::create(['pos_modifier_id' => 6, 'action' => RecipeAdjustmentAction::Add, 'ingredient_id' => $i['Biji Kopi']->id, 'qty' => 18]);
            ModifierRecipeAdjustment::create(['pos_modifier_id' => 5, 'action' => RecipeAdjustmentAction::Replace, 'ingredient_id' => $i['Oat Milk']->id, 'replaces_ingredient_id' => $i['Susu Sapi']->id]);
            ModifierRecipeAdjustment::create(['pos_modifier_id' => 3, 'action' => RecipeAdjustmentAction::Multiply, 'factor' => 1.3]);
        }
    }

    /**
     * @param  array<string, Ingredient>  $i
     */
    protected function seedInitialStock(array $i): void
    {
        if (\App\Models\StockReceipt::count() > 0) {
            return;
        }

        $supplier = Supplier::firstOrCreate(['name' => 'Supplier Utama'], ['contact' => '0812-0000-0000']);

        // [bahan, jumlah (satuan beli), total harga]
        app(StockReceiptService::class)->create(
            ['supplier_id' => $supplier->id, 'received_at' => now()->toDateString(), 'note' => 'Stok awal'],
            [
                ['ingredient_id' => $i['Biji Kopi']->id, 'purchase_qty' => 3, 'total_price' => 270000],
                ['ingredient_id' => $i['Susu Sapi']->id, 'purchase_qty' => 2, 'total_price' => 360000],
                ['ingredient_id' => $i['Oat Milk']->id, 'purchase_qty' => 1, 'total_price' => 150000],
                ['ingredient_id' => $i['Gula Aren Cair']->id, 'purchase_qty' => 3, 'total_price' => 135000],
                ['ingredient_id' => $i['Cup 12oz']->id, 'purchase_qty' => 4, 'total_price' => 40000],
                ['ingredient_id' => $i['Tutup Cup']->id, 'purchase_qty' => 4, 'total_price' => 20000],
            ],
        );
    }
}
