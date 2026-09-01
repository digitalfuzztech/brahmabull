<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->enum('conversation_type', ['support', 'internal_direct', 'internal_group'])->default('support');
            $table->foreignId('player_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['bot', 'waiting', 'active', 'resolved'])->nullable();
            $table->string('name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direct_key')->nullable()->unique();
            $table->boolean('is_archived')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('human_requested_at')->nullable();
            $table->timestamp('first_staff_response_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_player_message_at')->nullable();
            $table->timestamp('last_staff_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['player_id', 'status']);
            $table->index(['status', 'assigned_to']);
            $table->index(['conversation_type', 'is_archived']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
