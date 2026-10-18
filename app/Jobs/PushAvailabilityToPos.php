<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Models\PosProduct;
use App\Services\LokalPosClient;
use App\Support\CurrentOutlet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim perubahan ketersediaan menu ke lokal_pos (PATCH /products/{id}/availability, kontrak v2.1).
 * Bila POS menjawab availability_source = manual, tanda habis manual pemilik dihormati: kita
 * menyimpannya di pos_products supaya tidak mencoba menandai tersedia lagi.
 */
class PushAvailabilityToPos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $outletId, public int $posProductId, public bool $available)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(CurrentOutlet $current): void
    {
        $outlet = Outlet::find($this->outletId);

        if (! $outlet || ! $outlet->hasPosConnection()) {
            return;
        }

        try {
            $result = LokalPosClient::for($outlet)->setAvailability($this->posProductId, $this->available);
        } catch (Throwable $e) {
            $this->tolerate($e);

            return;
        }

        $current->run($outlet, function () use ($result) {
            $product = PosProduct::where('pos_product_id', $this->posProductId)->first();

            if (! $product) {
                return;
            }

            $product->availability_source = $result['availability_source'];

            if ($result['availability_source'] === 'manual') {
                // Tanda habis manual milik pemilik: bukan urusan stok, jangan pernah ditimpa.
                $product->is_available = false;
                $product->auto_unavailable = false;
            } else {
                $product->is_available = $result['is_available'];
                $product->auto_unavailable = ! $result['is_available'];
            }

            $product->save();
        });
    }

    /**
     * Pada queue `sync` (lokal/test) job berjalan di dalam request/aksi pengguna. POS yang tidak bisa
     * dihubungi tidak boleh menggagalkan pencatatan stok, jadi galat dicatat saja. Di queue worker galat
     * dilempar ulang supaya job di-retry.
     */
    protected function tolerate(\Throwable $e): void
    {
        if (! $this->job instanceof SyncJob) {
            throw $e;
        }

        Log::warning(class_basename($this) . ' gagal (queue sync, diabaikan): ' . $e->getMessage(), ['outlet_id' => $this->outletId]);
    }
}
