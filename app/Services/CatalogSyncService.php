<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\PosModifier;
use App\Models\PosModifierGroup;
use App\Models\PosProduct;
use App\Support\CurrentOutlet;
use Illuminate\Support\Facades\DB;

/**
 * Menyinkronkan cache katalog (produk, grup & opsi kustomisasi) dari GET /catalog lokal_pos.
 */
class CatalogSyncService
{
    public function __construct(
        protected CurrentOutlet $current,
        protected MenuAvailabilityService $availability,
    ) {
    }

    /**
     * @return array{products: int, modifiers: int}
     */
    public function sync(Outlet $outlet, ?array $catalog = null): array
    {
        $catalog ??= LokalPosClient::for($outlet)->catalog();

        return $this->current->run($outlet, function () use ($outlet, $catalog) {
            $now = now();
            $productCount = 0;
            $modifierCount = 0;

            DB::transaction(function () use ($catalog, $outlet, $now, &$productCount, &$modifierCount) {
                foreach ($catalog['products'] ?? [] as $row) {
                    $product = PosProduct::firstOrNew(['pos_product_id' => $row['id']]);
                    $product->fill([
                        'name' => $row['name'],
                        'category' => $row['category'] ?? $product->category,
                        'price' => (int) ($row['price'] ?? 0),
                        'is_available' => (bool) ($row['is_available'] ?? true),
                        'synced_at' => $now,
                    ]);

                    // Nilai terbaru dari POS jadi acuan; tanda habis otomatis dihitung ulang di bawah.
                    if ($product->is_available) {
                        $product->auto_unavailable = false;
                    }

                    $product->save();
                    $productCount++;

                    DB::table('pos_product_modifiers')
                        ->where('outlet_id', $outlet->id)
                        ->where('pos_product_id', $row['id'])
                        ->delete();

                    foreach ($row['modifier_groups'] ?? [] as $group) {
                        PosModifierGroup::updateOrCreate(
                            ['pos_group_id' => $group['id']],
                            ['name' => $group['name'], 'synced_at' => $now]
                        );

                        foreach ($group['modifiers'] ?? [] as $modifier) {
                            PosModifier::updateOrCreate(
                                ['pos_modifier_id' => $modifier['id']],
                                [
                                    'pos_group_id' => $group['id'],
                                    'name' => $modifier['name'],
                                    'price_delta' => (int) ($modifier['price_delta'] ?? 0),
                                    'synced_at' => $now,
                                ]
                            );

                            DB::table('pos_product_modifiers')->insertOrIgnore([
                                'outlet_id' => $outlet->id,
                                'pos_product_id' => $row['id'],
                                'pos_modifier_id' => $modifier['id'],
                            ]);

                            $modifierCount++;
                        }
                    }
                }

                $outlet->forceFill(['last_catalog_sync_at' => $now])->save();
            });

            // Status ketersediaan dari POS bisa berbeda dari hitungan stok: samakan lagi.
            $this->availability->refreshAll();

            return ['products' => $productCount, 'modifiers' => $modifierCount];
        });
    }
}
