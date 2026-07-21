<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LokalPosIntegrationService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Process order created webhook from Lokal-POS
     */
    public function processOrderDeduction(array $orderPayload): array
    {
        $orderRef = $orderPayload['reference_no'] ?? $orderPayload['order_id'] ?? ('POS-' . time());
        $itemsDeducted = [];
        $errors = [];

        $orderItems = $orderPayload['items'] ?? [];

        foreach ($orderItems as $orderItem) {
            $identifier = $orderItem['pos_product_id'] ?? $orderItem['sku'] ?? $orderItem['name'] ?? null;
            $quantity = (float) ($orderItem['quantity'] ?? 1);

            if (!$identifier) {
                continue;
            }

            try {
                $movement = $this->stockService->recordPosStockDeduction(
                    $identifier,
                    $quantity,
                    $orderRef,
                    $orderItem['notes'] ?? ''
                );
                $itemsDeducted[] = [
                    'identifier' => $identifier,
                    'quantity' => $quantity,
                    'status' => 'success',
                    'new_balance' => $movement->quantity_after,
                ];
            } catch (Exception $e) {
                Log::error("POS Deduction failed for {$identifier}: " . $e->getMessage());
                $errors[] = [
                    'identifier' => $identifier,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'order_reference' => $orderRef,
            'deducted_count' => count($itemsDeducted),
            'items' => $itemsDeducted,
            'errors' => $errors,
        ];
    }

    /**
     * Format current stock balances for Lokal-POS consumption
     */
    public function getStockExportForPos(): array
    {
        $items = Item::with(['category', 'unit'])->where('is_active', true)->get();

        return $items->map(function ($item) {
            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'pos_product_id' => $item->pos_product_id,
                'name' => $item->name,
                'category' => $item->category?->name,
                'unit' => $item->unit?->symbol,
                'quantity_on_hand' => (float) $item->quantity_on_hand,
                'minimum_stock' => (float) $item->minimum_stock,
                'is_low_stock' => $item->isLowStock(),
                'in_stock' => $item->quantity_on_hand > 0,
            ];
        })->toArray();
    }
}
