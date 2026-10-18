<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     *
     * Pemilik: kelola bahan/resep/supplier/koneksi POS, lihat HPP & nilai stok, selesaikan opname.
     * Kasir: catat stok masuk, waste, dan isi hitungan opname.
     */
    public function boot(): void
    {
        Gate::define('manage-inventory', fn (User $user) => $user->isOwner());
        Gate::define('view-costs', fn (User $user) => $user->isOwner());
        Gate::define('complete-opname', fn (User $user) => $user->isOwner());
    }
}
