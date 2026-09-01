<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('participant_role', ['owner', 'member'])->default('member');
            $table->timestamp('joined_at', 6);
            $table->timestamp('left_at', 6)->nullable();
            $table->timestamp('last_read_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
            $table->index(['conversation_id', 'participant_role', 'left_at'], 'chat_participant_role_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversation_participants');
    }
};
