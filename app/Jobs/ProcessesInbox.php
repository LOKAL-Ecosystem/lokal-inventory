<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Models\WebhookInbox;
use App\Support\CurrentOutlet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dasar untuk job yang memproses satu baris webhook_inbox. Job tidak punya sesi pengguna,
 * jadi outlet diset eksplisit dari baris inbox (yang outlet-nya ditentukan lewat signature).
 */
abstract class ProcessesInbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function __construct(public int $outletId, public int $inboxId)
    {
    }

    /**
     * @param  array<string, mixed>  $data  isi "data" dari payload
     */
    abstract protected function process(Outlet $outlet, array $data): void;

    public function handle(CurrentOutlet $current): void
    {
        $outlet = Outlet::findOrFail($this->outletId);

        $current->run($outlet, function () use ($outlet) {
            $inbox = WebhookInbox::find($this->inboxId);

            if (! $inbox || $inbox->processed_at) {
                return;
            }

            $inbox->increment('attempts');

            try {
                $this->process($outlet, $inbox->payload['data'] ?? []);
            } catch (Throwable $e) {
                $inbox->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

                throw $e;
            }

            $inbox->forceFill(['processed_at' => now(), 'last_error' => null])->save();
        });
    }
}
