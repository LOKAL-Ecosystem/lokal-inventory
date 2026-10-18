<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Services\LokalPosClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim perubahan ketersediaan menu ke lokal_pos. Endpoint-nya belum ada di kontrak v2;
 * selama services.lokal_pos.availability_endpoint kosong, job hanya mencatat log.
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

    public function handle(): void
    {
        $outlet = Outlet::find($this->outletId);

        if (! $outlet || ! $outlet->hasPosConnection()) {
            return;
        }

        $sent = LokalPosClient::for($outlet)->setAvailability($this->posProductId, $this->available);

        if (! $sent) {
            Log::info('Ketersediaan menu berubah tetapi endpoint POS belum dikonfigurasi.', [
                'outlet_id' => $this->outletId,
                'pos_product_id' => $this->posProductId,
                'available' => $this->available,
            ]);
        }
    }
}
