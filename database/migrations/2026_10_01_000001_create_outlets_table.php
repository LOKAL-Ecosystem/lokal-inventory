<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('pos_outlet_id')->nullable()->unique();
            $table->string('pos_base_url')->nullable();
            $table->text('pos_api_key')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamp('last_catalog_sync_at')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
