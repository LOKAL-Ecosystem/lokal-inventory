<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('pos_product_id')->index(); // Link to POS product_id
            $table->string('pos_product_name')->nullable();
            $table->foreignId('stock_item_id')->constrained('items')->cascadeOnDelete(); // Link to raw ingredient item in inventory
            $table->decimal('quantity_needed', 12, 4);
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->unique(['pos_product_id', 'stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
