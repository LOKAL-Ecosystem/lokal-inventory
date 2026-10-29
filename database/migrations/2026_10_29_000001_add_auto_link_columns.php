<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penautan otomatis dari Lokal POS (kontrak v2.3). pos_outlet_id sudah unik sejak tabel dibuat.
        Schema::table('outlets', function (Blueprint $table) {
            $table->timestamp('linked_at')->nullable()->after('webhook_secret');
        });

        // User cermin dari staf POS: masuk lewat satu login, tanpa password.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('pos_user_id')->nullable()->after('outlet_id');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('password')->nullable()->change();

            $table->unique(['outlet_id', 'pos_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['outlet_id', 'pos_user_id']);
            $table->dropColumn(['pos_user_id', 'is_active']);
            $table->string('password')->nullable(false)->change();
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('linked_at');
        });
    }
};
