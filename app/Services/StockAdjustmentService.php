<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    /**
     * Create adjustment record and immediately apply stock mutation if auto-approve (e.g. by admin)
     */
    public function createAdjustment(array $data, array $itemsData, ?int $userId = null, bool $autoApprove = true): StockAdjustment
    {
        return DB::transaction(function () use ($data, $itemsData, $userId, $autoApprove) {
            $adjustment = StockAdjustment::create([
                'reference_no' => 'ADJ-' . date('YmdHis') . '-' . rand(100, 999),
                'user_id' => $userId,
                'approved_by' => $autoApprove ? $userId : null,
                'status' => $autoApprove ? 'approved' : 'pending',
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'approved_at' => $autoApprove ? now() : null,
            ]);

            foreach ($itemsData as $row) {
                $item = Item::findOrFail($row['item_id']);
                $systemQty = $item->quantity_on_hand;
                $actualQty = (float) $row['actual_quantity'];
                $diffQty = $actualQty - $systemQty;

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $item->id,
                    'system_quantity' => $systemQty,
                    'actual_quantity' => $actualQty,
                    'difference_quantity' => $diffQty,
                ]);

                if ($autoApprove) {
                    $this->applyItemAdjustment($item, $systemQty, $actualQty, $diffQty, $adjustment, $userId);
                }
            }

            return $adjustment;
        });
    }

    /**
     * Approve pending adjustment
     */
    public function approveAdjustment(StockAdjustment $adjustment, int $approverId): StockAdjustment
    {
        if ($adjustment->status !== 'pending') {
            return $adjustment;
        }

        return DB::transaction(function () use ($adjustment, $approverId) {
            $adjustment->load('items');

            foreach ($adjustment->items as $adjItem) {
                $item = $adjItem->item;
                $systemQty = $item->quantity_on_hand;
                $actualQty = $adjItem->actual_quantity;
                $diffQty = $actualQty - $systemQty;

                $this->applyItemAdjustment($item, $systemQty, $actualQty, $diffQty, $adjustment, $approverId);
            }

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $adjustment;
        });
    }

    private function applyItemAdjustment(Item $item, float $systemQty, float $actualQty, float $diffQty, StockAdjustment $adjustment, ?int $userId)
    {
        $item->quantity_on_hand = $actualQty;
        $item->save();

        $type = $diffQty >= 0 ? 'adjustment_add' : 'adjustment_sub';
        $reasonLabels = [
            'damaged' => 'Barang Rusak',
            'lost' => 'Barang Hilang / Rusak',
            'stock_opname_discrepancy' => 'Koreksi Stock Opname',
            'other' => 'Koreksi Manual',
        ];
        $reasonStr = $reasonLabels[$adjustment->reason] ?? 'Adjustment';

        StockMovement::create([
            'item_id' => $item->id,
            'type' => $type,
            'quantity_before' => $systemQty,
            'quantity_change' => $diffQty,
            'quantity_after' => $actualQty,
            'reference_no' => $adjustment->reference_no,
            'description' => "Penyesuaian Stok ({$reasonStr})" . ($adjustment->notes ? ": {$adjustment->notes}" : ''),
            'user_id' => $userId,
        ]);
    }
}
