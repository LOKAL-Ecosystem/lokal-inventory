<?php

namespace App\Providers;

use App\Support\CurrentOutlet;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu konteks outlet per request/job (di-reset otomatis oleh queue worker).
        $this->app->scoped(CurrentOutlet::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
