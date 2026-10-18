<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cache katalog dari lokal_pos. Hanya kolom stock_*, auto_unavailable, dan missing_* milik inventory.
        Schema::create('pos_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pos_product_id');
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('stock_sufficient')->default(true);
            $table->boolean('auto_unavailable')->default(false);
            $table->unsignedInteger('missing_recipe_hits')->default(0);
            $table->timestamp('last_missing_recipe_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'pos_product_id']);
        });

        Schema::create('pos_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pos_group_id');
            $table->string('name');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'pos_group_id']);
        });

        Schema::create('pos_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pos_modifier_id');
            $table->unsignedBigInteger('pos_group_id')->nullable();
            $table->string('name');
            $table->bigInteger('price_delta')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'pos_modifier_id']);
        });

        // Opsi kustomisasi yang dipakai tiap produk (dibutuhkan untuk mencocokkan nama saat payload tanpa modifier_id).
        Schema::create('pos_product_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pos_product_id');
            $table->unsignedBigInteger('pos_modifier_id');

            $table->unique(['outlet_id', 'pos_product_id', 'pos_modifier_id'], 'pos_product_modifiers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_product_modifiers');
        Schema::dropIfExists('pos_modifiers');
        Schema::dropIfExists('pos_modifier_groups');
        Schema::dropIfExists('pos_products');
    }
};
