<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_products', function (Blueprint $table) {
            // null (tersedia) | manual (pemilik menandai habis di POS) | inventory. Nilai terakhir dari POS.
            $table->string('availability_source', 20)->nullable()->after('is_available');
        });

        Schema::table('outlets', function (Blueprint $table) {
            // Hash daftar peringatan stok yang terakhir berhasil dikirim ke POS (PUT /stock-alerts).
            $table->string('stock_alerts_hash', 64)->nullable();
            $table->timestamp('stock_alerts_pushed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['stock_alerts_hash', 'stock_alerts_pushed_at']);
        });

        Schema::table('pos_products', function (Blueprint $table) {
            $table->dropColumn('availability_source');
        });
    }
};
