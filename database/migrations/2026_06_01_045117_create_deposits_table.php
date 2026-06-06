<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained();

            $table->foreignId('game_id')->constrained();

            $table->enum('wallet_type', [
                'cashapp',
                'paypal',
                'chime'
            ]);

            $table->decimal('amount', 10, 2);

            $table->string('proof_image');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->text('admin_notes')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
