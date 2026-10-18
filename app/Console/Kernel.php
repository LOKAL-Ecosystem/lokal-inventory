<?php

namespace App\Console;

use App\Jobs\ReconcileOrders;
use App\Jobs\SyncCatalogFromPos;
use App\Models\FormToken;
use App\Models\Outlet;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sinkron katalog berkala dan rekonsiliasi pesanan terlewat (aman diulang: idempoten).
        $schedule->call(function () {
            Outlet::all()->filter->hasPosConnection()
                ->each(fn (Outlet $outlet) => SyncCatalogFromPos::dispatch($outlet->id));
        })->hourly()->name('inventory-sync-catalog')->onOneServer();

        $schedule->call(function () {
            Outlet::all()->filter->hasPosConnection()
                ->each(fn (Outlet $outlet) => ReconcileOrders::dispatch($outlet->id));
        })->everyFifteenMinutes()->name('inventory-reconcile-orders')->onOneServer();

        // Token idempotensi form hanya perlu bertahan beberapa hari.
        $schedule->call(fn () => FormToken::withoutGlobalScopes()->where('created_at', '<', now()->subDays(2))->delete())
            ->daily()->name('inventory-prune-form-tokens');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
