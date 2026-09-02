<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->enum('conversation_type', ['support', 'internal_direct', 'internal_group', 'internal_channel'])
                ->default('support')
                ->change();
            $table->string('channel_key')->nullable()->unique()->after('direct_key');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropUnique(['channel_key']);
            $table->dropColumn('channel_key');
            $table->enum('conversation_type', ['support', 'internal_direct', 'internal_group'])
                ->default('support')
                ->change();
        });
    }
};
