<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token idempotensi sekali pakai untuk form yang mengubah stok (cegah kirim ganda).
        Schema::create('form_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('token');
            $table->string('action', 120);
            $table->text('result_url')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['outlet_id', 'token']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_tokens');
    }
};
