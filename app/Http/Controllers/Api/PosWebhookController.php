<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPosWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PosWebhookController extends Controller
{
    /**
     * Handle order completed webhook from Lokal-POS
     */
    public function handleOrderCompleted(Request $request): JsonResponse
    {
        return $this->processWebhookRequest($request, 'OrderCompleted');
    }

    /**
     * Handle stock manually adjusted webhook from Lokal-POS
     */
    public function handleStockAdjusted(Request $request): JsonResponse
    {
        return $this->processWebhookRequest($request, 'StockManuallyAdjusted');
    }

    /**
     * Generic endpoint supporting event_type in body/header
     */
    public function handleGenericWebhook(Request $request): JsonResponse
    {
        $eventType = $request->input('event_type') 
            ?? $request->header('X-Event-Type') 
            ?? 'OrderCompleted';

        return $this->processWebhookRequest($request, $eventType);
    }

    /**
     * Shared Webhook & Idempotency processing logic
     */
    protected function processWebhookRequest(Request $request, string $defaultEventType): JsonResponse
    {
        $payload = $request->all();
        $idempotencyKey = $payload['idempotency_key'] 
            ?? $request->header('X-Idempotency-Key') 
            ?? ($payload['data']['idempotency_key'] ?? null);

        if (empty($idempotencyKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing idempotency_key in request body or header.',
            ], 422);
        }

        $eventType = $payload['event_type'] ?? $defaultEventType;

        // Idempotency check: look up existing webhook event
        $existingEvent = WebhookEvent::where('idempotency_key', $idempotencyKey)->first();

        if ($existingEvent) {
            if ($existingEvent->status === 'processed') {
                Log::info("Duplicate webhook received & skipped (already processed). Idempotency key: {$idempotencyKey}");
                return response()->json([
                    'success' => true,
                    'message' => 'already_processed',
                    'idempotency_key' => $idempotencyKey,
                ], 200);
            }

            // Retry processing if previously received/failed
            ProcessPosWebhookJob::dispatch($existingEvent->id);

            return response()->json([
                'success' => true,
                'message' => 'received',
                'idempotency_key' => $idempotencyKey,
            ], 200);
        }

        // Store new webhook event
        $webhookEvent = WebhookEvent::create([
            'idempotency_key' => $idempotencyKey,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'received',
        ]);

        // Dispatch background job for stock deduction
        ProcessPosWebhookJob::dispatch($webhookEvent->id);

        // Fast response back to POS before queue completes
        return response()->json([
            'success' => true,
            'message' => 'received',
            'idempotency_key' => $idempotencyKey,
        ], 200);
    }
}
