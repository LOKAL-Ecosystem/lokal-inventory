<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessConfirmedOrder;
use App\Jobs\ProcessVoidedOrder;
use App\Jobs\SyncCatalogFromPos;
use App\Models\Outlet;
use App\Models\WebhookInbox;
use App\Support\CurrentOutlet;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penerima webhook dari Lokal POS (kontrak v2). Urutannya: verifikasi signature -> simpan ke
 * webhook_inbox -> balas cepat -> proses di job. Outlet ditentukan dari signature (secret milik
 * outlet), bukan dari isi payload.
 */
class LokalPosWebhookController extends Controller
{
    /** Event yang memengaruhi stok / katalog. Selain ini hanya disimpan dan ditandai selesai. */
    protected const JOBS = [
        'order.confirmed' => ProcessConfirmedOrder::class,
        'order.voided' => ProcessVoidedOrder::class,
    ];

    public function __invoke(Request $request, CurrentOutlet $current): JsonResponse
    {
        $body = $request->getContent();
        $signature = (string) ($request->header('X-Webhook-Signature') ?? $request->header('X-Signature') ?? '');

        $outlet = $this->resolveOutlet($body, $signature);

        if (! $outlet) {
            Log::warning('Webhook Lokal POS ditolak: signature tidak valid.', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Signature tidak valid.'], 401);
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Body bukan JSON yang valid.'], 400);
        }

        $eventType = (string) ($payload['event_type'] ?? $request->header('X-Event-Type') ?? '');
        $eventId = (string) ($payload['idempotency_key'] ?? $request->header('X-Idempotency-Key') ?? '');

        if ($eventType === '' || $eventId === '') {
            return response()->json(['success' => false, 'message' => 'event_type / idempotency_key wajib ada.'], 422);
        }

        return $current->run($outlet, function () use ($outlet, $payload, $eventType, $eventId) {
            try {
                $inbox = WebhookInbox::create([
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'received_at' => now(),
                ]);
            } catch (QueryException $e) {
                if (in_array($e->getCode(), ['23000', '23505'], true)) {
                    return response()->json(['success' => true, 'message' => 'Duplikat, diabaikan.', 'duplicate' => true]);
                }

                throw $e;
            }

            $this->dispatchProcessing($outlet, $inbox);

            return response()->json(['success' => true, 'message' => 'Diterima.'], 202);
        });
    }

    protected function resolveOutlet(string $body, string $signature): ?Outlet
    {
        if ($signature === '') {
            return null;
        }

        return Outlet::query()
            ->whereNotNull('webhook_secret')
            ->get()
            ->first(fn (Outlet $outlet) => hash_equals(hash_hmac('sha256', $body, $outlet->webhook_secret), $signature));
    }

    protected function dispatchProcessing(Outlet $outlet, WebhookInbox $inbox): void
    {
        try {
            if ($job = self::JOBS[$inbox->event_type] ?? null) {
                $job::dispatch($outlet->id, $inbox->id);
            } elseif ($inbox->event_type === 'catalog.updated') {
                $productIds = array_values(array_filter(
                    (array) ($inbox->payload['data']['product_ids'] ?? []),
                    fn ($id) => is_int($id) || ctype_digit((string) $id)
                ));

                SyncCatalogFromPos::dispatch($outlet->id, $inbox->id, array_map('intval', $productIds));
            } else {
                // order.created, payment.paid, dll.: tidak ada efek ke stok.
                $inbox->forceFill(['processed_at' => now()])->save();
            }
        } catch (Throwable $e) {
            // Pada queue sync, kegagalan proses naik ke sini. Event sudah aman di inbox (last_error terisi)
            // dan bisa diproses ulang, jadi tetap balas 2xx supaya POS tidak mengirim ulang tanpa henti.
            Log::error('Pemrosesan webhook gagal: ' . $e->getMessage(), ['inbox_id' => $inbox->id]);
        }
    }
}
