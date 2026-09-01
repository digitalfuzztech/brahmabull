<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_support_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
            $table->enum('event_type', [
                'play_reminder',
                'deposit_reminder',
                'brahma_deposit_reminder',
                'cashout_reminder',
                'human_support_requested',
            ]);
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->enum('status', ['pending', 'handled'])->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
            $table->index(['player_id', 'event_type', 'related_type', 'related_id'], 'chat_support_event_lookup');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_support_events');
    }
};
