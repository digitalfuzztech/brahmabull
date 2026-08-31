<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brahma_play_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->decimal('points_to_load', 12, 2);
            $table->decimal('balance_at_submission', 12, 2);
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('game_username')->nullable();
            $table->string('game_password')->nullable();
            $table->text('rejection_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('debited_at')->nullable();
            $table->decimal('balance_before_debit', 12, 2)->nullable();
            $table->decimal('balance_after_debit', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brahma_play_requests');
    }
};
