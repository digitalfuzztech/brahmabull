<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brahma_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wallet_type')->nullable();
            $table->string('wallet_name')->nullable();
            $table->string('wallet_account_identifier')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('proof_image');
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->decimal('load_balance', 12, 2)->nullable();
            $table->decimal('balance_before_credit', 12, 2)->nullable();
            $table->decimal('balance_after_credit', 12, 2)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('wallet_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brahma_deposits');
    }
};
