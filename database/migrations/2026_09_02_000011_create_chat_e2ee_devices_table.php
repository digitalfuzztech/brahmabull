<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_e2ee_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_uuid', 64);
            $table->string('device_name', 100)->nullable();
            $table->string('public_encryption_key', 128);
            $table->string('public_signing_key', 128);
            $table->string('key_fingerprint', 128)->unique();
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid']);
            $table->index(['user_id', 'trusted_at', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_e2ee_devices');
    }
};
