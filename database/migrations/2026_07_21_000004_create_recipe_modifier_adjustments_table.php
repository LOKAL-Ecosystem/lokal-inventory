<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_modifier_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_product_id')->index();
            $table->unsignedBigInteger('pos_modifier_id')->index();
            $table->string('pos_modifier_name');
            $table->foreignId('stock_item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('adjustment_type', ['override', 'add', 'subtract']);
            $table->decimal('adjustment_qty', 12, 4);
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->unique(['pos_product_id', 'pos_modifier_id', 'stock_item_id'], 'recipe_mod_adj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_modifier_adjustments');
    }
};
