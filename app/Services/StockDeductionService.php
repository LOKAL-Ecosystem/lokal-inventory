<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Outlet;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\WebhookInbox;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Potong stok dari pesanan POS yang lunas (sale) dan kembalikan saat di-void (void_return).
 * Semua jalur idempoten: andalkan unique constraint stock_movements + pengecekan per baris pesanan.
 */
class StockDeductionService
{
    public const SOURCE_TYPE = 'pos_order_item';

    public function __construct(
        protected StockService $stock,
        protected RecipeUsageCalculator $usage,
        protected MenuAvailabilityService $availability,
        protected PosOrderNormalizer $normalizer,
    ) {
    }

    /**
     * Event order.confirmed. $data = isi "data" webhook atau satu pesanan dari REST.
     *
     * @param  array<string, mixed>  $data
     * @return int jumlah pergerakan baru
     */
    public function processConfirmed(array $data, ?Outlet $outlet = null): int
    {
        if (empty($data['items']) && ($data['order_id'] ?? $data['id'] ?? null) && $outlet) {
            // Payload tanpa item: ambil detail lengkap dari API.
            $data = LokalPosClient::for($outlet)->order($data['order_id'] ?? $data['id']);
        }

        return $this->deductOrder($this->normalizer->normalize($data));
    }

    /**
     * Event order.voided. Bila sale-nya belum tercatat (event tidak berurutan), proses dulu
     * confirmed dari inbox, atau ambil detail pesanan dari API. Gagal -> exception agar job di-retry.
     *
     * @param  array<string, mixed>  $data
     * @return int jumlah void_return baru
     */
    public function processVoided(array $data, ?Outlet $outlet = null): int
    {
        $uuid = $data['order_id'] ?? $data['id'] ?? $data['uuid'] ?? null;

        if (! $uuid) {
            throw new RuntimeException('Payload order.voided tidak memuat order_id.');
        }

        if (! $this->hasSale($uuid)) {
            $this->ensureSaleRecorded($uuid, $outlet);
        }

        return $this->returnOrder($uuid);
    }

    /**
     * @param  array{uuid: string|null, items: array<int, array<string, mixed>>}  $order
     */
    public function deductOrder(array $order): int
    {
        if (! $order['uuid']) {
            throw new RuntimeException('Pesanan tanpa order_id tidak bisa diproses.');
        }

        $created = 0;
        $touched = [];

        DB::transaction(function () use ($order, &$created, &$touched) {
            foreach ($order['items'] as $index => $item) {
                $sourceId = "{$order['uuid']}#{$index}";

                // Satu baris pesanan hanya sekali, apa pun perubahan resep sesudahnya.
                $already = StockMovement::query()
                    ->where('type', StockMovementType::Sale->value)
                    ->where('source_type', self::SOURCE_TYPE)
                    ->where('source_id', $sourceId)
                    ->exists();

                if ($already || $item['product_id'] === null) {
                    continue;
                }

                $product = PosProduct::firstOrCreate(
                    ['pos_product_id' => $item['product_id']],
                    ['name' => $item['name'] ?: "Produk #{$item['product_id']}", 'price' => $item['price'] ?? 0]
                );

                $usage = $this->usage->usage($item['product_id'], $item['modifier_ids'], $item['quantity']);

                if ($usage === []) {
                    // Tanpa resep: dilewati (tidak error), dicatat untuk laporan "menu tanpa resep".
                    $product->increment('missing_recipe_hits');
                    $product->forceFill(['last_missing_recipe_at' => now()])->save();

                    continue;
                }

                foreach ($usage as $ingredientId => $qty) {
                    $movement = $this->stock->record(
                        $ingredientId,
                        StockMovementType::Sale,
                        -$qty,
                        self::SOURCE_TYPE,
                        $sourceId,
                        ['pos_order_uuid' => $order['uuid']],
                    );

                    if ($movement) {
                        $created++;
                        $touched[$ingredientId] = true;
                    }
                }
            }
        });

        $this->availability->refreshForIngredients(array_keys($touched));

        return $created;
    }

    /**
     * Kembalikan semua pergerakan sale pesanan dengan jumlah persis sama (berdasarkan ledger).
     */
    public function returnOrder(string $uuid): int
    {
        $created = 0;
        $touched = [];

        DB::transaction(function () use ($uuid, &$created, &$touched) {
            $sales = StockMovement::query()
                ->where('type', StockMovementType::Sale->value)
                ->where('pos_order_uuid', $uuid)
                ->get();

            foreach ($sales as $sale) {
                $movement = $this->stock->record(
                    $sale->ingredient_id,
                    StockMovementType::VoidReturn,
                    -(float) $sale->qty,
                    $sale->source_type,
                    $sale->source_id,
                    ['pos_order_uuid' => $uuid],
                );

                if ($movement) {
                    $created++;
                    $touched[$sale->ingredient_id] = true;
                }
            }
        });

        $this->availability->refreshForIngredients(array_keys($touched));

        return $created;
    }

    protected function hasSale(string $uuid): bool
    {
        return StockMovement::query()
            ->where('type', StockMovementType::Sale->value)
            ->where('pos_order_uuid', $uuid)
            ->exists();
    }

    protected function ensureSaleRecorded(string $uuid, ?Outlet $outlet): void
    {
        $pending = WebhookInbox::query()
            ->where('event_id', "order.confirmed:{$uuid}")
            ->first();

        if ($pending && ! $pending->processed_at) {
            $this->processConfirmed($pending->payload['data'] ?? [], $outlet);
            $pending->forceFill(['processed_at' => now(), 'last_error' => null])->save();

            return;
        }

        if ($pending) {
            // Confirmed sudah diproses sebelumnya; pesanan memang tidak memotong stok (mis. tanpa resep).
            return;
        }

        if (! $outlet) {
            throw new RuntimeException("Sale untuk pesanan {$uuid} belum tercatat dan outlet tidak diketahui.");
        }

        // Confirmed tidak pernah diterima: ambil dari API (gagal -> job di-retry).
        $order = LokalPosClient::for($outlet)->order($uuid);
        $this->processConfirmed($order);
    }
}
