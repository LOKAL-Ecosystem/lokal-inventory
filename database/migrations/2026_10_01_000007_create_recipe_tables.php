<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pos_product_id');
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
            $table->timestamps();

            $table->unique(['outlet_id', 'pos_product_id', 'ingredient_id']);
        });

        Schema::create('modifier_recipe_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pos_modifier_id');
            $table->string('action', 10);
            $table->foreignId('ingredient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3)->nullable();
            $table->decimal('factor', 10, 4)->nullable();
            $table->foreignId('replaces_ingredient_id')->nullable()->constrained('ingredients')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['outlet_id', 'pos_modifier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_recipe_adjustments');
        Schema::dropIfExists('recipe_items');
    }
};
