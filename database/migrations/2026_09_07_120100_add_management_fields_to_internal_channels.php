<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chat_conversations', 'channel_description')) {
            Schema::table('chat_conversations', function (Blueprint $table): void {
                $table->text('channel_description')->nullable()->after('channel_key');
            });
        }
        if (! Schema::hasColumn('chat_conversations', 'channel_mode')) {
            Schema::table('chat_conversations', function (Blueprint $table): void {
                $table->string('channel_mode', 24)->default('read_only')->after('channel_description');
            });
        }
        if (! Schema::hasColumn('chat_conversation_participants', 'channel_can_post')) {
            Schema::table('chat_conversation_participants', function (Blueprint $table): void {
                $table->boolean('channel_can_post')->default(false)->after('participant_role');
            });
        }
        if (! Schema::hasColumn('chat_conversation_participants', 'channel_blocked_at')) {
            Schema::table('chat_conversation_participants', function (Blueprint $table): void {
                $table->timestamp('channel_blocked_at')->nullable()->after('channel_can_post');
            });
        }
        Schema::table('chat_conversation_participants', function (Blueprint $table): void {
            $table->index(['conversation_id', 'channel_blocked_at'], 'chat_participants_channel_blocked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversation_participants', function (Blueprint $table): void {
            $table->dropIndex('chat_participants_channel_blocked_idx');
            $table->dropColumn(['channel_can_post', 'channel_blocked_at']);
        });
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropColumn(['channel_description', 'channel_mode']);
        });
    }
};
