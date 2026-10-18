<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\StockReceipt;
use Illuminate\Support\Facades\DB;

class StockReceiptService
{
    public function __construct(
        protected StockService $stock,
        protected MenuAvailabilityService $availability,
    ) {
    }

    /**
     * Catat belanja. Tiap item: jumlah dalam satuan beli dan total harga baris (rupiah).
     * Harga per satuan dasar = total harga / jumlah dalam satuan dasar.
     *
     * @param  array{supplier_id?: int|null, received_at: string, note?: string|null}  $header
     * @param  array<int, array{ingredient_id: int, purchase_qty: float|int|string, total_price: int|string}>  $items
     */
    public function create(array $header, array $items, ?int $userId = null): StockReceipt
    {
        $receipt = DB::transaction(function () use ($header, $items, $userId) {
            $receipt = StockReceipt::create([
                'supplier_id' => $header['supplier_id'] ?? null,
                'received_at' => $header['received_at'],
                'note' => $header['note'] ?? null,
                'created_by' => $userId,
            ]);

            $total = 0;

            foreach ($items as $row) {
                $ingredient = Ingredient::findOrFail($row['ingredient_id']);
                $purchaseQty = (float) $row['purchase_qty'];
                $baseQty = round($purchaseQty * (float) $ingredient->purchase_unit_qty, 3);
                $totalPrice = (int) $row['total_price'];

                $item = $receipt->items()->create([
                    'ingredient_id' => $ingredient->id,
                    'purchase_qty' => $purchaseQty,
                    'base_qty' => $baseQty,
                    'total_price' => $totalPrice,
                ]);

                $this->stock->record(
                    $ingredient,
                    StockMovementType::Purchase,
                    $baseQty,
                    'stock_receipt_item',
                    (string) $item->id,
                    [
                        'unit_cost' => $baseQty > 0 ? $totalPrice / $baseQty : 0,
                        'created_by' => $userId,
                        'note' => $header['note'] ?? null,
                    ],
                );

                $total += $totalPrice;
            }

            $receipt->update(['total_cost' => $total]);

            return $receipt;
        });

        $this->availability->refreshForIngredients(collect($items)->pluck('ingredient_id')->all());

        return $receipt->load('items.ingredient');
    }
}
