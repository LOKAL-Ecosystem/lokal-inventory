<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['outlet_id', 'name']);
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('base_unit', 10);
            $table->string('purchase_unit', 40)->nullable();
            $table->decimal('purchase_unit_qty', 14, 3)->default(1);
            $table->decimal('avg_cost', 14, 4)->default(0);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['outlet_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('ingredient_categories');
    }
};
