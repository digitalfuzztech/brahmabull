<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_accounts', function (Blueprint $table) {

            $table->id();

            // Player
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Game
            $table->foreignId('game_id')
                ->constrained()
                ->cascadeOnDelete();

            // Username assigned by agent
            $table->string('game_username');

            // Agent who assigned it
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Prevent duplicate username for same game
            $table->unique([
                'game_id',
                'game_username'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_accounts');
    }
};
