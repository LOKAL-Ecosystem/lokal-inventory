<?php

namespace App\Jobs;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\UnmappedProduct;
use App\Models\WebhookEvent;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPosWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $webhookEventId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $webhookEventId)
    {
        $this->webhookEventId = $webhookEventId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhookEvent = WebhookEvent::find($this->webhookEventId);

        if (!$webhookEvent || $webhookEvent->status === 'processed') {
            return;
        }

        try {
            DB::transaction(function () use ($webhookEvent) {
                $payload = $webhookEvent->payload;
                $eventType = $webhookEvent->event_type;

                if (in_array($eventType, ['OrderCompleted', 'order.completed'])) {
                    $this->processOrderCompleted($payload);
                } elseif (in_array($eventType, ['StockManuallyAdjusted', 'stock.manually_adjusted'])) {
                    $this->processStockAdjusted($payload);
                } else {
                    Log::warning("Unknown webhook event_type received: {$eventType}", ['payload' => $payload]);
                }

                $webhookEvent->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error_message' => null,
                ]);
            });
        } catch (Exception $e) {
            Log::error("Failed to process WebhookEvent #{$this->webhookEventId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            $webhookEvent->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process OrderCompleted event logic
     */
    protected function processOrderCompleted(array $payload): void
    {
        // Support nested 'data' payload structure or flat structure
        $data = $payload['data'] ?? $payload;
        $transactionId = $data['transaction_id'] ?? $data['invoice_number'] ?? $payload['idempotency_key'] ?? ('TRX-' . time());
        $items = $data['items'] ?? [];

        foreach ($items as $orderItem) {
            $productId = (string) ($orderItem['product_id'] ?? $orderItem['pos_product_id'] ?? $orderItem['item_id'] ?? '');
            $productName = $orderItem['product_name'] ?? $orderItem['name'] ?? ('Product #' . $productId);
            $orderQty = (float) ($orderItem['quantity'] ?? $orderItem['qty'] ?? 1);

            if (empty($productId)) {
                continue;
            }

            $modifierIds = [];
            $rawModifiers = $orderItem['modifier_ids'] ?? $orderItem['modifiers'] ?? [];
            if (is_array($rawModifiers)) {
                foreach ($rawModifiers as $m) {
                    if (is_array($m)) {
                        $modifierIds[] = $m['id'] ?? $m['pos_modifier_id'] ?? null;
                    } elseif (is_object($m)) {
                        $modifierIds[] = $m->id ?? $m->pos_modifier_id ?? null;
                    } else {
                        $modifierIds[] = $m;
                    }
                }
            }
            $modifierIds = array_filter(array_map('intval', $modifierIds));

            // Instantiate calculator service
            $calculator = app(\App\Services\RecipeCalculatorService::class);
            $deductions = $calculator->calculateDeduction($productId, $orderQty, $modifierIds);

            if (empty($deductions)) {
                // If there's no base recipe or modifier recipe, record as unmapped
                $recipeExists = Recipe::where('pos_product_id', $productId)->exists();
                if (!$recipeExists) {
                    Log::warning("Unmapped product received in POS Order webhook. No recipe found for product_id: {$productId} ({$productName})");

                    UnmappedProduct::updateOrCreate(
                        ['pos_product_id' => $productId],
                        [
                            'product_name' => $productName,
                            'last_transaction_id' => (string) $transactionId,
                            'occurrence_count' => DB::raw('occurrence_count + 1'),
                            'last_seen_at' => now(),
                        ]
                    );
                }
                continue;
            }

            // Deduct stock for each raw ingredient determined by RecipeCalculatorService
            foreach ($deductions as $stockItemId => $qtyDeducted) {
                $stockItem = Item::find($stockItemId);
                if (!$stockItem) {
                    continue;
                }

                $quantityBefore = (float) $stockItem->quantity_on_hand;
                $quantityAfter = $quantityBefore - $qtyDeducted;

                // Update stock balance
                $stockItem->update([
                    'quantity_on_hand' => $quantityAfter,
                ]);

                // Record stock movement
                StockMovement::create([
                    'item_id' => $stockItem->id,
                    'type' => 'stock_out_pos',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => -$qtyDeducted,
                    'quantity_after' => $quantityAfter,
                    'reference_no' => (string) $transactionId,
                    'description' => "POS Order Deduction: {$productName} (x{$orderQty}) via Webhook" . (empty($modifierIds) ? "" : " [Modifiers: " . implode(',', $modifierIds) . "]"),
                ]);

                // Check for negative stock warning
                if ($quantityAfter < 0) {
                    Log::alert("STOK MINUS DETECTED: Stock item '{$stockItem->name}' (ID: {$stockItem->id}) balance is negative ({$quantityAfter} {$stockItem->unit?->symbol}) after POS Order transaction #{$transactionId}. Manual opname required!");
                }
            }
        }
    }

    /**
     * Process StockManuallyAdjusted event logic
     */
    protected function processStockAdjusted(array $payload): void
    {
        $data = $payload['data'] ?? $payload;
        $productId = (string) ($data['product_id'] ?? '');
        $changeAmount = (float) ($data['change_amount'] ?? 0);
        $reason = $data['reason'] ?? 'POS Manual Adjustment';

        if (empty($productId) || $changeAmount == 0) {
            return;
        }

        // Check if directly mapped to Item pos_product_id
        $stockItem = Item::where('pos_product_id', $productId)->first();
        if ($stockItem) {
            $quantityBefore = (float) $stockItem->quantity_on_hand;
            $quantityAfter = $quantityBefore + $changeAmount;

            $stockItem->update([
                'quantity_on_hand' => $quantityAfter,
            ]);

            StockMovement::create([
                'item_id' => $stockItem->id,
                'type' => $changeAmount > 0 ? 'adjustment_add' : 'adjustment_sub',
                'quantity_before' => $quantityBefore,
                'quantity_change' => $changeAmount,
                'quantity_after' => $quantityAfter,
                'reference_no' => $payload['idempotency_key'] ?? ('ADJ-' . time()),
                'description' => "POS Manual Adjustment via Webhook: {$reason}",
            ]);
        }
    }
}
