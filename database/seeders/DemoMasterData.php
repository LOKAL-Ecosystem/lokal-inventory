<?php

namespace Database\Seeders;

use App\Enums\BaseUnit;
use App\Enums\RecipeAdjustmentAction;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\ModifierRecipeAdjustment;
use App\Models\Outlet;
use App\Models\PosModifier;
use App\Models\PosModifierGroup;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use App\Models\Supplier;
use App\Support\CurrentOutlet;
use Illuminate\Support\Facades\DB;

/**
 * Data induk demo untuk satu outlet: katalog yang sama seperti seeder lokal_pos (id produk, grup,
 * dan opsi mengikuti urutan seeder POS; sinkron katalog akan menimpa dengan data asli), bahan baku,
 * resep, penyesuaian opsi, dan supplier. Dipakai InventoryDemoSeeder; riwayat stok ada di sana.
 */
class DemoMasterData
{
    /**
     * Katalog lokal_pos: id => [nama, kategori, harga, grup opsi].
     * Grup: 1 Ukuran, 2 Gula, 3 Es, 4 Susu, 5 Tambahan.
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: array<int>}>
     */
    public const PRODUCTS = [
        1 => ['Espresso Single', 'Coffee', 18000, [1]],
        2 => ['Americano', 'Coffee', 20000, [1, 2, 3]],
        3 => ['Cafe Latte', 'Coffee', 25000, [1, 2, 3, 4, 5]],
        4 => ['Cappuccino', 'Coffee', 25000, [1, 2, 3, 4]],
        5 => ['Kopi Susu Aren', 'Coffee', 22000, [1, 2, 3, 5]],
        6 => ['Caramel Macchiato', 'Coffee', 30000, [1, 2, 3, 4]],
        7 => ['Cold Brew', 'Coffee', 24000, [1, 3]],
        8 => ['Matcha Latte', 'Non-Coffee', 28000, [1, 2, 3, 4]],
        9 => ['Chocolate Classic', 'Non-Coffee', 26000, [1, 2, 3]],
        10 => ['Thai Tea', 'Non-Coffee', 20000, [1, 2, 3, 5]],
        11 => ['Lemon Tea', 'Non-Coffee', 18000, [1, 2, 3]],
        12 => ['Croissant Butter', 'Pastry', 22000, []],
        13 => ['Cinnamon Roll', 'Pastry', 24000, []],
        14 => ['Almond Croissant', 'Pastry', 28000, []],
        15 => ['Nasi Goreng Lokal', 'Food', 32000, []],
        16 => ['Sandwich Tuna', 'Food', 30000, []],
        17 => ['Mie Goreng Spesial', 'Food', 30000, []],
        18 => ['Chicken Rice Bowl', 'Food', 34000, []],
        19 => ['Roti Bakar Coklat Keju', 'Snack', 20000, []],
        20 => ['Kentang Goreng', 'Snack', 18000, []],
        21 => ['Pisang Goreng Madu', 'Snack', 16000, []],
        22 => ['Orange Juice', 'Juice', 22000, [1, 3]],
        23 => ['Alpukat Kocok', 'Juice', 26000, [1, 3, 5]],
        24 => ['Flat White', 'Coffee', 27000, [1, 2, 3, 4]],
        25 => ['V60 Manual Brew', 'Coffee', 28000, [1]],
        26 => ['Hazelnut Latte', 'Coffee', 29000, [1, 2, 3, 4, 5]],
        27 => ['Brown Sugar Boba', 'Non-Coffee', 27000, [1, 2, 3, 5]],
        28 => ['Taro Latte', 'Non-Coffee', 26000, [1, 2, 3, 4]],
        29 => ['Brownies Coklat', 'Pastry', 20000, []],
        30 => ['Es Kopi Pandan', 'Coffee', 23000, []],
        31 => ['Red Velvet Latte', 'Non-Coffee', 28000, []],
    ];

    /** Ditandai habis manual oleh pemilik di POS. */
    public const MANUALLY_SOLD_OUT = [30, 31];

    /**
     * Grup dan opsi lokal_pos: grup => [nama, wajib, [opsi id => [nama, selisih harga]]].
     *
     * @var array<int, array{0: string, 1: bool, 2: array<int, array{0: string, 1: int}>}>
     */
    public const MODIFIER_GROUPS = [
        1 => ['Ukuran', true, [1 => ['Regular', 0], 2 => ['Large', 5000]]],
        2 => ['Gula', true, [3 => ['Normal', 0], 4 => ['Less sugar', 0], 5 => ['No sugar', 0]]],
        3 => ['Es', true, [6 => ['Normal ice', 0], 7 => ['Less ice', 0], 8 => ['No ice', 0]]],
        4 => ['Susu', false, [9 => ['Susu sapi', 0], 10 => ['Oat milk', 8000], 11 => ['Almond milk', 8000]]],
        5 => ['Tambahan', false, [12 => ['Extra shot', 8000], 13 => ['Whipped cream', 5000], 14 => ['Boba', 6000]]],
    ];

    /**
     * Bahan: nama => [kategori, satuan dasar, satuan beli, isi per satuan beli, stok minimum, harga per satuan beli, supplier].
     *
     * @var array<string, array{0: string, 1: BaseUnit, 2: string, 3: int, 4: int, 5: int, 6: string}>
     */
    public const INGREDIENTS = [
        'Biji Kopi' => ['Kopi', BaseUnit::Gram, 'kg', 1000, 1500, 180000, 'Roastery Nusantara'],
        'Biji Kopi Single Origin' => ['Kopi', BaseUnit::Gram, 'pack', 250, 500, 95000, 'Roastery Nusantara'],
        'Konsentrat Cold Brew' => ['Kopi', BaseUnit::Milliliter, 'botol', 1000, 1500, 85000, 'Roastery Nusantara'],
        'Susu Sapi' => ['Susu & Dairy', BaseUnit::Milliliter, 'karton', 12000, 6000, 216000, 'CV Susu Segar Lembang'],
        'Oat Milk' => ['Susu & Dairy', BaseUnit::Milliliter, 'karton', 6000, 1500, 270000, 'CV Susu Segar Lembang'],
        'Almond Milk' => ['Susu & Dairy', BaseUnit::Milliliter, 'karton', 6000, 1000, 300000, 'CV Susu Segar Lembang'],
        'Whipped Cream' => ['Susu & Dairy', BaseUnit::Gram, 'kotak', 1000, 300, 95000, 'CV Susu Segar Lembang'],
        'Keju Cheddar' => ['Susu & Dairy', BaseUnit::Gram, 'blok', 2000, 400, 190000, 'CV Susu Segar Lembang'],
        'Mentega' => ['Susu & Dairy', BaseUnit::Gram, 'blok', 1000, 250, 85000, 'CV Susu Segar Lembang'],
        'Susu Kental Manis' => ['Susu & Dairy', BaseUnit::Gram, 'kaleng', 370, 370, 13000, 'Grosir Sembako Makmur'],
        'Gula Aren Cair' => ['Pemanis & Sirup', BaseUnit::Milliliter, 'botol', 1000, 1000, 45000, 'Toko Bahan Sentosa'],
        'Gula Pasir' => ['Pemanis & Sirup', BaseUnit::Gram, 'kg', 1000, 1000, 17000, 'Grosir Sembako Makmur'],
        'Sirup Karamel' => ['Pemanis & Sirup', BaseUnit::Milliliter, 'botol', 750, 300, 140000, 'Toko Bahan Sentosa'],
        'Sirup Hazelnut' => ['Pemanis & Sirup', BaseUnit::Milliliter, 'botol', 750, 300, 140000, 'Toko Bahan Sentosa'],
        'Sirup Brown Sugar' => ['Pemanis & Sirup', BaseUnit::Milliliter, 'botol', 1000, 500, 60000, 'Toko Bahan Sentosa'],
        'Sirup Pandan' => ['Pemanis & Sirup', BaseUnit::Milliliter, 'botol', 750, 300, 55000, 'Toko Bahan Sentosa'],
        'Madu' => ['Pemanis & Sirup', BaseUnit::Milliliter, 'botol', 650, 200, 90000, 'Toko Bahan Sentosa'],
        'Bubuk Matcha' => ['Bubuk & Teh', BaseUnit::Gram, 'pack', 500, 150, 250000, 'Toko Bahan Sentosa'],
        'Bubuk Coklat' => ['Bubuk & Teh', BaseUnit::Gram, 'pack', 1000, 300, 120000, 'Toko Bahan Sentosa'],
        'Bubuk Thai Tea' => ['Bubuk & Teh', BaseUnit::Gram, 'pack', 500, 200, 75000, 'Toko Bahan Sentosa'],
        'Bubuk Taro' => ['Bubuk & Teh', BaseUnit::Gram, 'pack', 1000, 300, 110000, 'Toko Bahan Sentosa'],
        'Jeruk Peras' => ['Buah & Topping', BaseUnit::Gram, 'kg', 1000, 2000, 25000, 'Pasar Induk - Bu Ani'],
        'Alpukat' => ['Buah & Topping', BaseUnit::Gram, 'kg', 1000, 1000, 35000, 'Pasar Induk - Bu Ani'],
        'Pisang' => ['Buah & Topping', BaseUnit::Piece, 'sisir', 12, 12, 30000, 'Pasar Induk - Bu Ani'],
        'Boba' => ['Buah & Topping', BaseUnit::Gram, 'pack', 1000, 500, 45000, 'Toko Bahan Sentosa'],
        'Es Batu' => ['Buah & Topping', BaseUnit::Gram, 'kantong', 5000, 10000, 12000, 'Es Kristal Segar'],
        'Croissant Frozen' => ['Bakery', BaseUnit::Piece, 'pack', 10, 10, 120000, 'Frozen Bakery Co.'],
        'Almond Croissant Frozen' => ['Bakery', BaseUnit::Piece, 'pack', 10, 8, 160000, 'Frozen Bakery Co.'],
        'Cinnamon Roll Frozen' => ['Bakery', BaseUnit::Piece, 'pack', 6, 6, 90000, 'Frozen Bakery Co.'],
        'Brownies Potong' => ['Bakery', BaseUnit::Piece, 'loyang', 16, 8, 160000, 'Frozen Bakery Co.'],
        'Roti Tawar' => ['Bakery', BaseUnit::Piece, 'pack', 20, 20, 22000, 'Grosir Sembako Makmur'],
        'Selai Coklat' => ['Bakery', BaseUnit::Gram, 'toples', 1000, 200, 65000, 'Grosir Sembako Makmur'],
        'Beras' => ['Makanan', BaseUnit::Gram, 'karung', 5000, 3000, 75000, 'Grosir Sembako Makmur'],
        'Telur' => ['Makanan', BaseUnit::Piece, 'tray', 30, 30, 60000, 'Grosir Sembako Makmur'],
        'Ayam Fillet' => ['Makanan', BaseUnit::Gram, 'kg', 1000, 1500, 55000, 'Pasar Induk - Bu Ani'],
        'Mie Telur' => ['Makanan', BaseUnit::Gram, 'pack', 1000, 500, 30000, 'Grosir Sembako Makmur'],
        'Tuna Kaleng' => ['Makanan', BaseUnit::Gram, 'kaleng', 185, 370, 28000, 'Grosir Sembako Makmur'],
        'Kentang Beku' => ['Makanan', BaseUnit::Gram, 'pack', 2500, 2000, 85000, 'Frozen Bakery Co.'],
        'Minyak Goreng' => ['Makanan', BaseUnit::Milliliter, 'jeriken', 5000, 2000, 90000, 'Grosir Sembako Makmur'],
        'Bumbu Nasi Goreng' => ['Makanan', BaseUnit::Gram, 'pack', 1000, 300, 60000, 'Grosir Sembako Makmur'],
        'Sayur Campur' => ['Makanan', BaseUnit::Gram, 'kg', 1000, 500, 20000, 'Pasar Induk - Bu Ani'],
        'Saus Sambal' => ['Makanan', BaseUnit::Milliliter, 'botol', 1000, 300, 35000, 'Grosir Sembako Makmur'],
        'Tepung Bumbu' => ['Makanan', BaseUnit::Gram, 'kg', 1000, 300, 25000, 'Grosir Sembako Makmur'],
        'Cup 12oz' => ['Kemasan', BaseUnit::Piece, 'sleeve', 50, 50, 50000, 'Distributor Kemasan Jaya'],
        'Cup Plastik 16oz' => ['Kemasan', BaseUnit::Piece, 'sleeve', 50, 150, 45000, 'Distributor Kemasan Jaya'],
        'Tutup Cup' => ['Kemasan', BaseUnit::Piece, 'sleeve', 50, 200, 25000, 'Distributor Kemasan Jaya'],
        'Sedotan' => ['Kemasan', BaseUnit::Piece, 'pack', 100, 100, 15000, 'Distributor Kemasan Jaya'],
    ];

    /** Bahan yang tidak dipakai lagi (menu Es Kopi Pandan berhenti dijual). */
    public const INACTIVE_INGREDIENTS = ['Sirup Pandan'];

    public const SUPPLIERS = [
        'Roastery Nusantara' => 'Pak Dimas - 0812-3456-7801',
        'CV Susu Segar Lembang' => 'Bu Rina - 0813-2233-4455',
        'Toko Bahan Sentosa' => 'Koh Andi - 0821-7788-9900',
        'Pasar Induk - Bu Ani' => 'Bu Ani - 0857-1122-3344',
        'Frozen Bakery Co.' => 'order@frozenbakery.test',
        'Grosir Sembako Makmur' => 'Pak Haji Udin - 0818-5566-7788',
        'Distributor Kemasan Jaya' => 'Sales: Mbak Tika - 0896-4433-2211',
        'Es Kristal Segar' => 'Pak Joko - 0852-9988-7766',
    ];

    /**
     * Isi data induk untuk satu outlet. Idempoten: katalog di-upsert (sinkron dari POS menimpanya),
     * bahan/resep/supplier dibuat bila belum ada.
     */
    public function seed(Outlet $outlet): void
    {
        app(CurrentOutlet::class)->run($outlet, function () {
            $this->seedCatalog();
            $ingredients = $this->seedIngredients();
            $this->seedRecipes($ingredients);
            $this->seedSuppliers();
        });
    }

    protected function seedCatalog(): void
    {
        foreach (self::PRODUCTS as $id => [$name, $category, $price]) {
            $manual = in_array($id, self::MANUALLY_SOLD_OUT, true);

            PosProduct::updateOrCreate(['pos_product_id' => $id], [
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'is_available' => ! $manual,
                'availability_source' => $manual ? 'manual' : null,
                'synced_at' => now(),
            ]);
        }

        foreach (self::MODIFIER_GROUPS as $groupId => [$groupName, , $modifiers]) {
            PosModifierGroup::updateOrCreate(['pos_group_id' => $groupId], ['name' => $groupName, 'synced_at' => now()]);

            foreach ($modifiers as $modifierId => [$modifierName, $delta]) {
                PosModifier::updateOrCreate(['pos_modifier_id' => $modifierId], [
                    'pos_group_id' => $groupId, 'name' => $modifierName, 'price_delta' => $delta, 'synced_at' => now(),
                ]);
            }
        }

        foreach (self::PRODUCTS as $productId => [, , , $groups]) {
            foreach ($groups as $groupId) {
                foreach (array_keys(self::MODIFIER_GROUPS[$groupId][2]) as $modifierId) {
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
        $categories = collect(self::INGREDIENTS)->pluck(0)->unique()->values()
            ->mapWithKeys(fn ($name) => [$name => IngredientCategory::firstOrCreate(['name' => $name])->id]);

        $result = [];
        foreach (self::INGREDIENTS as $name => [$category, $unit, $purchaseUnit, $purchaseQty, $min]) {
            $result[$name] = Ingredient::firstOrCreate(['name' => $name], [
                'ingredient_category_id' => $categories[$category],
                'base_unit' => $unit,
                'purchase_unit' => $purchaseUnit,
                'purchase_unit_qty' => $purchaseQty,
                'min_stock' => $min,
                'is_active' => ! in_array($name, self::INACTIVE_INGREDIENTS, true),
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, Ingredient>  $i
     */
    protected function seedRecipes(array $i): void
    {
        $iced = [['Cup Plastik 16oz', 1], ['Tutup Cup', 1], ['Sedotan', 1]];
        $hot = [['Cup 12oz', 1], ['Tutup Cup', 1]];

        // Lemon Tea (11) sengaja belum punya resep, supaya laporan "menu tanpa resep" terisi.
        // Es Kopi Pandan (30) dan Red Velvet Latte (31) ditandai habis manual di POS.
        $recipes = [
            1 => [['Biji Kopi', 18], ...$hot],
            2 => [['Biji Kopi', 18], ['Es Batu', 150], ...$iced],
            3 => [['Biji Kopi', 18], ['Susu Sapi', 150], ['Es Batu', 120], ...$iced],
            4 => [['Biji Kopi', 18], ['Susu Sapi', 120], ['Es Batu', 100], ...$iced],
            5 => [['Biji Kopi', 18], ['Susu Sapi', 100], ['Gula Aren Cair', 25], ['Es Batu', 120], ...$iced],
            6 => [['Biji Kopi', 18], ['Susu Sapi', 150], ['Sirup Karamel', 20], ['Es Batu', 120], ...$iced],
            7 => [['Konsentrat Cold Brew', 150], ['Es Batu', 150], ...$iced],
            8 => [['Bubuk Matcha', 15], ['Susu Sapi', 180], ['Gula Pasir', 10], ['Es Batu', 120], ...$iced],
            9 => [['Bubuk Coklat', 30], ['Susu Sapi', 180], ['Gula Pasir', 10], ['Es Batu', 120], ...$iced],
            10 => [['Bubuk Thai Tea', 25], ['Susu Kental Manis', 30], ['Gula Pasir', 10], ['Es Batu', 150], ...$iced],
            12 => [['Croissant Frozen', 1], ['Mentega', 10]],
            13 => [['Cinnamon Roll Frozen', 1]],
            14 => [['Almond Croissant Frozen', 1]],
            15 => [['Beras', 150], ['Telur', 1], ['Ayam Fillet', 50], ['Bumbu Nasi Goreng', 20], ['Minyak Goreng', 15], ['Sayur Campur', 30]],
            16 => [['Roti Tawar', 2], ['Tuna Kaleng', 60], ['Keju Cheddar', 20], ['Mentega', 10], ['Sayur Campur', 20]],
            17 => [['Mie Telur', 100], ['Telur', 1], ['Ayam Fillet', 40], ['Sayur Campur', 30], ['Minyak Goreng', 15], ['Saus Sambal', 10]],
            18 => [['Beras', 150], ['Ayam Fillet', 120], ['Tepung Bumbu', 30], ['Minyak Goreng', 40], ['Saus Sambal', 15], ['Sayur Campur', 30]],
            19 => [['Roti Tawar', 2], ['Selai Coklat', 30], ['Keju Cheddar', 20], ['Mentega', 10]],
            20 => [['Kentang Beku', 150], ['Minyak Goreng', 50], ['Saus Sambal', 15]],
            21 => [['Pisang', 2], ['Tepung Bumbu', 40], ['Minyak Goreng', 40], ['Madu', 15]],
            22 => [['Jeruk Peras', 400], ['Gula Pasir', 10], ['Es Batu', 100], ...$iced],
            23 => [['Alpukat', 200], ['Susu Kental Manis', 30], ['Susu Sapi', 100], ['Es Batu', 100], ...$iced],
            24 => [['Biji Kopi', 36], ['Susu Sapi', 120], ['Es Batu', 80], ...$iced],
            25 => [['Biji Kopi Single Origin', 15], ...$hot],
            26 => [['Biji Kopi', 18], ['Susu Sapi', 150], ['Sirup Hazelnut', 20], ['Es Batu', 120], ...$iced],
            27 => [['Susu Sapi', 180], ['Sirup Brown Sugar', 30], ['Boba', 60], ['Es Batu', 100], ...$iced],
            28 => [['Bubuk Taro', 30], ['Susu Sapi', 180], ['Gula Pasir', 10], ['Es Batu', 120], ...$iced],
            29 => [['Brownies Potong', 1]],
        ];

        foreach ($recipes as $productId => $items) {
            foreach ($items as [$ingredient, $qty]) {
                RecipeItem::updateOrCreate(
                    ['pos_product_id' => $productId, 'ingredient_id' => $i[$ingredient]->id],
                    ['qty' => $qty]
                );
            }
        }

        if (ModifierRecipeAdjustment::exists()) {
            return;
        }

        $multiply = RecipeAdjustmentAction::Multiply;

        // Large: hanya isi minuman × 1,3 (kemasan tetap satu); susu pengganti ikut karena replace diterapkan lebih dulu.
        $large = ['Biji Kopi', 'Konsentrat Cold Brew', 'Susu Sapi', 'Oat Milk', 'Almond Milk', 'Es Batu', 'Jeruk Peras', 'Alpukat'];

        $adjustments = [
            ...array_map(fn ($name) => [2, $multiply, $name, null, null, 1.3], $large),
            [4, $multiply, 'Gula Pasir', null, null, 0.5],               // Less sugar
            [4, $multiply, 'Gula Aren Cair', null, null, 0.5],
            [5, $multiply, 'Gula Pasir', null, null, 0],                 // No sugar
            [5, $multiply, 'Gula Aren Cair', null, null, 0],
            [7, $multiply, 'Es Batu', null, null, 0.5],                  // Less ice
            [8, $multiply, 'Es Batu', null, null, 0],                    // No ice
            [10, RecipeAdjustmentAction::Replace, 'Oat Milk', 'Susu Sapi', null, null],
            [11, RecipeAdjustmentAction::Replace, 'Almond Milk', 'Susu Sapi', null, null],
            [12, RecipeAdjustmentAction::Add, 'Biji Kopi', null, 18, null],     // Extra shot
            [13, RecipeAdjustmentAction::Add, 'Whipped Cream', null, 30, null], // Whipped cream
            [14, RecipeAdjustmentAction::Add, 'Boba', null, 50, null],          // Boba
        ];

        foreach ($adjustments as [$modifierId, $action, $ingredient, $replaces, $qty, $factor]) {
            ModifierRecipeAdjustment::create([
                'pos_modifier_id' => $modifierId,
                'action' => $action,
                'ingredient_id' => $ingredient ? $i[$ingredient]->id : null,
                'replaces_ingredient_id' => $replaces ? $i[$replaces]->id : null,
                'qty' => $qty,
                'factor' => $factor,
            ]);
        }
    }

    protected function seedSuppliers(): void
    {
        foreach (self::SUPPLIERS as $name => $contact) {
            Supplier::firstOrCreate(['name' => $name], ['contact' => $contact]);
        }
    }
}
