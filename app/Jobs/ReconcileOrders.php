<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Services\LokalPosClient;
use App\Services\StockDeductionService;
use App\Support\CurrentOutlet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengambil pesanan confirmed/voided terbaru dari lokal_pos dan memproses yang terlewat.
 * Aman dijalankan berulang karena semua jalurnya idempoten.
 */
class ReconcileOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $outletId, public int $lookbackHours = 48)
    {
    }

    public function handle(StockDeductionService $deduction, CurrentOutlet $current): void
    {
        $outlet = Outlet::findOrFail($this->outletId);

        if (! $outlet->hasPosConnection()) {
            return;
        }

        $current->run($outlet, function () use ($outlet, $deduction) {
            $client = LokalPosClient::for($outlet);
            $since = ($outlet->last_reconciled_at?->copy()->subHour() ?? now()->subHours($this->lookbackHours))->toIso8601String();
            $startedAt = now();

            foreach (['confirmed', 'voided'] as $status) {
                $page = 1;

                do {
                    $orders = $client->orders(['status' => $status, 'since' => $since, 'per_page' => 100, 'page' => $page]);

                    foreach ($orders as $order) {
                        try {
                            $status === 'confirmed'
                                ? $deduction->processConfirmed($order, $outlet)
                                : $deduction->processVoided($order, $outlet);
                        } catch (Throwable $e) {
                            Log::warning('Rekonsiliasi pesanan gagal: ' . $e->getMessage(), ['order' => $order['id'] ?? null]);
                        }
                    }

                    $page++;
                } while (count($orders) >= 100 && $page <= 50);
            }

            $outlet->forceFill(['last_reconciled_at' => $startedAt])->save();

            // Samakan juga lonceng stok di POS (dikirim ulang walau daftar tidak berubah).
            PushStockAlerts::dispatch($outlet->id, force: true);
        });
    }
}
