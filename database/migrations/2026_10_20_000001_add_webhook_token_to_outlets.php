<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            // Penanda outlet di URL webhook (/webhooks/lokal-pos/{token}). Bukan rahasia; signature tetap wajib.
            $table->string('webhook_token', 64)->nullable()->unique();
        });

        DB::table('outlets')->whereNull('webhook_token')->pluck('id')->each(
            fn ($id) => DB::table('outlets')->where('id', $id)->update(['webhook_token' => Str::random(40)])
        );
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropUnique(['webhook_token']);
            $table->dropColumn('webhook_token');
        });
    }
};
