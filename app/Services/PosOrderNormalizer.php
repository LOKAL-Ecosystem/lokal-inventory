<?php

namespace App\Services;

use App\Models\PosModifier;
use App\Models\PosProduct;
use App\Support\CurrentOutlet;
use Illuminate\Support\Facades\DB;

/**
 * Menyeragamkan data pesanan dari dua sumber: payload webhook (order_id, modifiers[].modifier_id)
 * dan REST GET /orders (id, modifiers tanpa modifier_id). Bila modifier_id tidak ada, opsi dicocokkan
 * lewat nama dengan cache katalog produk tersebut.
 */
class PosOrderNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{uuid: string|null, status: string|null, items: array<int, array{product_id: int|null, name: string, quantity: float, modifier_ids: array<int>}>}
     */
    public function normalize(array $data): array
    {
        $items = [];

        foreach ($data['items'] ?? [] as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;

            $items[] = [
                'product_id' => $productId,
                'name' => (string) ($item['name'] ?? ''),
                'price' => (int) ($item['unit_price'] ?? 0),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'modifier_ids' => $this->modifierIds($productId, $item['modifiers'] ?? []),
            ];
        }

        return [
            'uuid' => $data['order_id'] ?? $data['id'] ?? $data['uuid'] ?? null,
            'status' => $data['status'] ?? null,
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $modifiers
     * @return array<int>
     */
    protected function modifierIds(?int $productId, array $modifiers): array
    {
        $ids = [];

        foreach ($modifiers as $modifier) {
            if (! empty($modifier['modifier_id'])) {
                $ids[] = (int) $modifier['modifier_id'];

                continue;
            }

            $id = $this->resolveByName($productId, (string) ($modifier['name'] ?? ''));

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function resolveByName(?int $productId, string $name): ?int
    {
        if ($productId === null || $name === '') {
            return null;
        }

        $allowed = PosProduct::query()->where('pos_product_id', $productId)->exists()
            ? DB::table('pos_product_modifiers')
                ->where('outlet_id', app(CurrentOutlet::class)->id())
                ->where('pos_product_id', $productId)
                ->pluck('pos_modifier_id')
            : collect();

        return PosModifier::query()
            ->whereIn('pos_modifier_id', $allowed)
            ->where('name', $name)
            ->value('pos_modifier_id');
    }
}
