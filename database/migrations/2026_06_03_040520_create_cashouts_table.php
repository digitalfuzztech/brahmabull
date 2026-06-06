<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashouts', function (Blueprint $table) {

            $table->id();

            // Cashout Reference
            $table->string('reference')->unique();

            // Player
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Game
            $table->foreignId('game_id')
                ->constrained()
                ->cascadeOnDelete();

            // Assigned game username
            $table->foreignId('game_account_id')
                ->constrained()
                ->cascadeOnDelete();

            // Requested amount
            $table->decimal('amount', 10, 2);

            // Wallet Type
            $table->string('wallet_type');

            // Cashtag / Wallet ID
            $table->string('wallet_address')
                ->nullable();

            // Optional QR Upload
            $table->string('qr_image')
                ->nullable();

            // Status
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'paid',
            ])->default('pending');

            // Agent who verified
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')
                ->nullable();

            // Payment completed timestamp
            $table->timestamp('paid_at')
                ->nullable();

            // Optional notes
            $table->text('remarks')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashouts');
    }
};
