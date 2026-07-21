<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unmapped_products', function (Blueprint $table) {
            $table->id();
            $table->string('pos_product_id')->unique();
            $table->string('product_name')->nullable();
            $table->string('last_transaction_id')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmapped_products');
    }
};
