<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->timestamp('e2ee_rotation_required_at')->nullable()->after('current_key_version');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropColumn('e2ee_rotation_required_at');
        });
    }
};
