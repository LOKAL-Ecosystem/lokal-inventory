<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo mandiri (tanpa Lokal POS): outlet "Lokal Coffee" (pos_outlet_id 1, sama dengan seeder lokal_pos)
 * dengan akun lokal ber-password, lalu data induk + riwayat stok lewat InventoryDemoSeeder.
 *
 * Bila Lokal POS kemudian menautkan outlet 1 (php artisan lokal:link-inventory di POS), outlet ini
 * diperbarui di tempat dan akun dengan email yang sama diambil alih menjadi user cermin staf POS.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = Outlet::firstOrCreate(['pos_outlet_id' => 1], [
            'name' => 'Lokal Coffee',
            'pos_base_url' => 'http://127.0.0.1:8000',
        ]);

        // Akun sama dengan seeder lokal_pos; password hanya dipakai saat penautan mati atau APP_ENV=local.
        foreach ([
            ['Owner Lokal', 'admin@lokal.com', UserRole::Owner],
            ['Kasir Lokal', 'kasir@lokal.com', UserRole::Cashier],
            ['Kasir Dua', 'kasir2@lokal.com', UserRole::Cashier],
        ] as [$name, $email, $role]) {
            User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => $role,
                'outlet_id' => $outlet->id,
            ]);
        }

        $this->call(InventoryDemoSeeder::class);
    }
}
