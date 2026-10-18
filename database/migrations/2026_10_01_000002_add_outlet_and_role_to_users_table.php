<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('cashier')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outlet_id');
            $table->dropColumn('role');
        });
    }
};
