<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Users
        $admin = User::create([
            'name' => 'Admin Inventory',
            'email' => 'admin@lokal.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $staff = User::create([
            'name' => 'Staff Gudang',
            'email' => 'staff@lokal.id',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        // 2. Create Units
        $kg = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg']);
        $gram = Unit::create(['name' => 'Gram', 'symbol' => 'g']);
        $liter = Unit::create(['name' => 'Liter', 'symbol' => 'L']);
        $pcs = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs']);
        $pack = Unit::create(['name' => 'Pack / Dus', 'symbol' => 'pack']);

        // 3. Create Categories
        $catBahanBaku = Category::create(['name' => 'Bahan Baku Makanan', 'slug' => 'bahan-baku-makanan', 'description' => 'Tepung, beras, minyak, bumbu']);
        $catMinuman = Category::create(['name' => 'Bahan Minuman', 'slug' => 'bahan-minuman', 'description' => 'Kopi, teh, sirup, susu']);
        $catKemasan = Category::create(['name' => 'Kemasan & Packaging', 'slug' => 'kemasan-packaging', 'description' => 'Cup, thinwall, kantong plastik']);

        // 4. Create Suppliers
        $sup1 = Supplier::create([
            'name' => 'PT Sumber Kopi Nusantara',
            'contact_person' => 'Budi Santoso',
            'phone' => '0812-3456-7890',
            'email' => 'sales@sumberkopi.co.id',
            'address' => 'Jl. Industri No. 45, Bandung',
        ]);

        $sup2 = Supplier::create([
            'name' => 'CV Sembako Jaya Utama',
            'contact_person' => 'Siti Aminah',
            'phone' => '0857-1122-3344',
            'email' => 'order@sembakojaya.com',
            'address' => 'Pasar Induk Kramat Jati Blok A1',
        ]);

        // 5. Create Items & Initial Stock
        $itemsData = [
            [
                'sku' => 'RAW-KOP-001',
                'name' => 'Biji Kopi Arabika House Blend',
                'category_id' => $catMinuman->id,
                'unit_id' => $kg->id,
                'supplier_id' => $sup1->id,
                'quantity_on_hand' => 15.50,
                'minimum_stock' => 5.00,
                'unit_cost' => 120000,
                'pos_product_id' => 'POS-KOP-001',
            ],
            [
                'sku' => 'RAW-SUS-002',
                'name' => 'Susu UHT Full Cream 1L',
                'category_id' => $catMinuman->id,
                'unit_id' => $pack->id,
                'supplier_id' => $sup2->id,
                'quantity_on_hand' => 3.00, // Low stock!
                'minimum_stock' => 10.00,
                'unit_cost' => 18500,
                'pos_product_id' => 'POS-SUS-002',
            ],
            [
                'sku' => 'RAW-SIR-003',
                'name' => 'Sirup Vanila Premium 750ml',
                'category_id' => $catMinuman->id,
                'unit_id' => $liter->id,
                'supplier_id' => $sup1->id,
                'quantity_on_hand' => 8.00,
                'minimum_stock' => 2.00,
                'unit_cost' => 85000,
                'pos_product_id' => 'POS-SIR-003',
            ],
            [
                'sku' => 'RAW-TPG-004',
                'name' => 'Tepung Terigu Cakra Kembar 1kg',
                'category_id' => $catBahanBaku->id,
                'unit_id' => $kg->id,
                'supplier_id' => $sup2->id,
                'quantity_on_hand' => 2.00, // Low stock!
                'minimum_stock' => 15.00,
                'unit_cost' => 13500,
                'pos_product_id' => 'POS-TPG-004',
            ],
            [
                'sku' => 'PKG-CUP-005',
                'name' => 'Paper Cup 12oz Cold/Hot',
                'category_id' => $catKemasan->id,
                'unit_id' => $pcs->id,
                'supplier_id' => $sup2->id,
                'quantity_on_hand' => 250.00,
                'minimum_stock' => 50.00,
                'unit_cost' => 650,
                'pos_product_id' => 'POS-CUP-005',
            ],
        ];

        foreach ($itemsData as $data) {
            $item = Item::create($data);

            // Log initial stock movement
            StockMovement::create([
                'item_id' => $item->id,
                'type' => 'initial',
                'quantity_before' => 0,
                'quantity_change' => $item->quantity_on_hand,
                'quantity_after' => $item->quantity_on_hand,
                'reference_no' => 'INIT-STOCK',
                'description' => 'Saldo Awal Stok Sistem',
                'user_id' => $admin->id,
            ]);
        }
    }
}
