<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Models\WebhookInbox;
use App\Services\CatalogSyncService;
use App\Support\CurrentOutlet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dipicu webhook catalog.updated, scheduler, atau tombol sinkron manual.
 */
class SyncCatalogFromPos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $outletId, public ?int $inboxId = null)
    {
    }

    public function handle(CatalogSyncService $sync, CurrentOutlet $current): void
    {
        $outlet = Outlet::findOrFail($this->outletId);

        $current->run($outlet, function () use ($sync, $outlet) {
            $inbox = $this->inboxId ? WebhookInbox::find($this->inboxId) : null;

            try {
                $sync->sync($outlet);
            } catch (Throwable $e) {
                $inbox?->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

                throw $e;
            }

            $inbox?->forceFill(['processed_at' => now(), 'last_error' => null])->save();
        });
    }
}
