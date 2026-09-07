<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE chat_conversation_participants '
            .'MODIFY joined_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE chat_conversation_participants '
            .'MODIFY joined_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) '
            .'ON UPDATE CURRENT_TIMESTAMP(6)'
        );
    }
};
