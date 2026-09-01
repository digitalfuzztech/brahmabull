<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->enum('sender_type', ['player', 'bot', 'admin', 'agent', 'system']);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('message_type', ['text', 'options', 'system'])->default('text');
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('read_by_player_at')->nullable();
            $table->timestamp('read_by_staff_at')->nullable();
            $table->timestamps(6);

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'reply_to_message_id']);
            $table->index(['conversation_id', 'read_by_player_at']);
            $table->index(['conversation_id', 'read_by_staff_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
