<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger append-only: tidak pernah di-update atau dihapus.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained();
            $table->string('type', 20);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('source_type', 40);
            $table->string('source_id', 80);
            $table->string('pos_order_uuid', 64)->nullable()->index();
            $table->string('reason', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['outlet_id', 'type', 'source_type', 'source_id', 'ingredient_id'],
                'stock_movements_idempotency'
            );
            $table->index(['outlet_id', 'ingredient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
