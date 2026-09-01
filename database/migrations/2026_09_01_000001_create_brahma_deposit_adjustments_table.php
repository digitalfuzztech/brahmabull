<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brahma_deposit_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brahma_deposit_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_load_balance', 12, 2);
            $table->decimal('new_load_balance', 12, 2);
            $table->decimal('delta', 12, 2);
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['brahma_deposit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brahma_deposit_adjustments');
    }
};
