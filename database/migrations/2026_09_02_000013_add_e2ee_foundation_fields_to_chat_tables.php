<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->string('encryption_mode', 32)->nullable()->after('is_archived');
            $table->timestamp('e2ee_enabled_at')->nullable()->after('encryption_mode');
            $table->unsignedInteger('current_key_version')->nullable()->after('e2ee_enabled_at');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->uuid('client_message_uuid')->nullable()->unique()->after('message_type');
            $table->longText('encrypted_payload')->nullable()->after('body');
            $table->unsignedSmallInteger('encryption_version')->nullable()->after('encrypted_payload');
            $table->unsignedInteger('key_version')->nullable()->after('encryption_version');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropUnique(['client_message_uuid']);
            $table->dropColumn(['client_message_uuid', 'encrypted_payload', 'encryption_version', 'key_version']);
        });

        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropColumn(['encryption_mode', 'e2ee_enabled_at', 'current_key_version']);
        });
    }
};
