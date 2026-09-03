<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_e2ee_conversation_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('chat_e2ee_devices')->cascadeOnDelete();
            $table->unsignedInteger('key_version');
            $table->text('wrapped_key');
            $table->string('wrapping_algorithm', 50);
            $table->unsignedSmallInteger('format_version');
            $table->timestamps();

            $table->unique(['conversation_id', 'device_id', 'key_version'], 'chat_e2ee_keys_conversation_device_version_unique');
            $table->index(['conversation_id', 'key_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_e2ee_conversation_keys');
    }
};
