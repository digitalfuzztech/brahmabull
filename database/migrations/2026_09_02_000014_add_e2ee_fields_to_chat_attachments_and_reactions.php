<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_message_attachments', function (Blueprint $table): void {
            $table->uuid('client_attachment_uuid')->nullable()->unique()->after('message_id');
            $table->boolean('is_encrypted')->default(false)->after('duration');
            $table->longText('encrypted_key')->nullable()->after('is_encrypted');
            $table->longText('encrypted_metadata')->nullable()->after('encrypted_key');
            $table->unsignedSmallInteger('encryption_version')->nullable()->after('encrypted_metadata');
            $table->unsignedInteger('key_version')->nullable()->after('encryption_version');
            $table->unsignedBigInteger('ciphertext_size')->nullable()->after('key_version');
        });

        Schema::table('chat_message_reactions', function (Blueprint $table): void {
            $table->longText('encrypted_reaction')->nullable()->after('reaction');
            $table->unsignedSmallInteger('encryption_version')->nullable()->after('encrypted_reaction');
            $table->unsignedInteger('key_version')->nullable()->after('encryption_version');
        });
    }

    public function down(): void
    {
        Schema::table('chat_message_reactions', function (Blueprint $table): void {
            $table->dropColumn(['encrypted_reaction', 'encryption_version', 'key_version']);
        });

        Schema::table('chat_message_attachments', function (Blueprint $table): void {
            $table->dropUnique(['client_attachment_uuid']);
            $table->dropColumn([
                'client_attachment_uuid',
                'is_encrypted',
                'encrypted_key',
                'encrypted_metadata',
                'encryption_version',
                'key_version',
                'ciphertext_size',
            ]);
        });
    }
};
