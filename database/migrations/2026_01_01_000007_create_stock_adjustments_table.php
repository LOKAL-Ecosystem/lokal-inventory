<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique(); // e.g., ADJ-20260721-001
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Creator
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // Approver
            $table->string('status')->default('approved'); // 'pending', 'approved', 'rejected'
            $table->string('reason'); // 'damaged', 'lost', 'stock_opname_discrepancy', 'other'
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_quantity', 12, 2);
            $table->decimal('actual_quantity', 12, 2);
            $table->decimal('difference_quantity', 12, 2); // actual - system
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
    }
};
