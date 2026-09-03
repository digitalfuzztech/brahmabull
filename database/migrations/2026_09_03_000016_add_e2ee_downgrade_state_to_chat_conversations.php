<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->foreignId('e2ee_disable_requested_by')->nullable()->after('e2ee_rotation_required_at')->constrained('users')->nullOnDelete();
            $table->timestamp('e2ee_disable_requested_at', 6)->nullable()->after('e2ee_disable_requested_by');
            $table->timestamp('e2ee_disabled_at', 6)->nullable()->after('e2ee_disable_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('e2ee_disable_requested_by');
            $table->dropColumn(['e2ee_disable_requested_at', 'e2ee_disabled_at']);
        });
    }
};
