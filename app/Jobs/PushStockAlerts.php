<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Services\LokalPosClient;
use App\Services\StockAlertService;
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
 * Mengirim daftar lengkap bahan hampir habis ke lonceng POS (PUT /stock-alerts, kontrak v2.1).
 * Daftar dihitung saat job berjalan (bukan saat dipanggil) dan hanya dikirim bila berbeda dari yang
 * terakhir berhasil terkirim. $force = true (rekonsiliasi berkala) mengirim ulang apa adanya.
 */
class PushStockAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $outletId, public bool $force = false)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(StockAlertService $alerts, CurrentOutlet $current): void
    {
        $outlet = Outlet::find($this->outletId);

        if (! $outlet || ! $outlet->hasPosConnection()) {
            return;
        }

        try {
            $current->run($outlet, function () use ($alerts, $outlet) {
                $list = $alerts->alerts();
                $hash = $alerts->hash($list);

                if (! $this->force && $outlet->stock_alerts_hash === $hash) {
                    return;
                }

                LokalPosClient::for($outlet)->replaceStockAlerts($list);

                $outlet->forceFill(['stock_alerts_hash' => $hash, 'stock_alerts_pushed_at' => now()])->save();
            });
        } catch (Throwable $e) {
            $this->tolerate($e);
        }
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
