<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

class StockService
{
    /**
     * Process stock in / purchasing transaction
     */
    public function recordStockIn(array $data, array $itemsData, ?int $userId = null): StockIn
    {
        return DB::transaction(function () use ($data, $itemsData, $userId) {
            $totalCost = 0;
            foreach ($itemsData as $row) {
                $totalCost += ($row['quantity'] * $row['unit_cost']);
            }

            $stockIn = StockIn::create([
                'reference_no' => 'STK-IN-' . date('YmdHis') . '-' . rand(100, 999),
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $userId,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'total_cost' => $totalCost,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsData as $row) {
                $item = Item::findOrFail($row['item_id']);
                $subtotal = $row['quantity'] * $row['unit_cost'];

                StockInItem::create([
                    'stock_in_id' => $stockIn->id,
                    'item_id' => $item->id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'subtotal' => $subtotal,
                ]);

                // Update item stock & cost
                $qtyBefore = $item->quantity_on_hand;
                $qtyChange = $row['quantity'];
                $qtyAfter = $qtyBefore + $qtyChange;

                $item->quantity_on_hand = $qtyAfter;
                $item->unit_cost = $row['unit_cost'];
                $item->save();

                // Log movement
                StockMovement::create([
                    'item_id' => $item->id,
                    'type' => 'stock_in',
                    'quantity_before' => $qtyBefore,
                    'quantity_change' => $qtyChange,
                    'quantity_after' => $qtyAfter,
                    'reference_no' => $stockIn->reference_no,
                    'description' => 'Pembelian / Restock dari Supplier',
                    'user_id' => $userId,
                ]);
            }

            return $stockIn;
        });
    }

    /**
     * Record automatic stock deduction from POS Order
     */
    public function recordPosStockDeduction(string $posProductIdOrSku, float $quantity, string $orderRef, string $customerNotes = ''): StockMovement
    {
        return DB::transaction(function () use ($posProductIdOrSku, $quantity, $orderRef, $customerNotes) {
            // Find item by pos_product_id or sku
            $item = Item::where('pos_product_id', $posProductIdOrSku)
                ->orWhere('sku', $posProductIdOrSku)
                ->first();

            if (!$item) {
                throw new Exception("Item dengan POS ID/SKU [{$posProductIdOrSku}] tidak ditemukan di Lokal Inventory.");
            }

            $qtyBefore = $item->quantity_on_hand;
            $qtyChange = -$quantity;
            $qtyAfter = $qtyBefore + $qtyChange;

            $item->quantity_on_hand = max(0, $qtyAfter);
            $item->save();

            return StockMovement::create([
                'item_id' => $item->id,
                'type' => 'stock_out_pos',
                'quantity_before' => $qtyBefore,
                'quantity_change' => $qtyChange,
                'quantity_after' => $qtyAfter,
                'reference_no' => $orderRef,
                'description' => "Pengurangan otomatis dari Transaksi POS: {$orderRef}" . ($customerNotes ? " ({$customerNotes})" : ''),
                'user_id' => null,
            ]);
        });
    }

    /**
     * Get low stock items count & collection
     */
    public function getLowStockItems()
    {
        return Item::with(['category', 'unit'])
            ->whereRaw('quantity_on_hand <= minimum_stock')
            ->where('is_active', true)
            ->get();
    }
}
