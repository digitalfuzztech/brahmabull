<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversation_participants', function (Blueprint $table): void {
            $table->unsignedBigInteger('last_read_message_id')
                ->nullable()
                ->after('last_read_at');

            $table->index(
                ['conversation_id', 'user_id', 'last_read_message_id'],
                'chat_participant_read_message_idx'
            );
        });

        Schema::table('chat_conversation_observer_reads', function (Blueprint $table): void {
            $table->unsignedBigInteger('last_read_message_id')
                ->nullable()
                ->after('last_read_at');
        });

        /*
         * Preserve existing read positions.
         *
         * For every participant that previously had a timestamp cursor,
         * store the highest message ID that existed at or before that cursor.
         */
        DB::table('chat_conversation_participants')
            ->whereNotNull('last_read_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $messageId = DB::table('chat_messages')
                        ->where('conversation_id', $row->conversation_id)
                        ->where('created_at', '<=', $row->last_read_at)
                        ->max('id');

                    DB::table('chat_conversation_participants')
                        ->where('id', $row->id)
                        ->update([
                            'last_read_message_id' => $messageId,
                        ]);
                }
            });

        DB::table('chat_conversation_observer_reads')
            ->whereNotNull('last_read_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $messageId = DB::table('chat_messages')
                        ->where('conversation_id', $row->conversation_id)
                        ->where('created_at', '<=', $row->last_read_at)
                        ->max('id');

                    DB::table('chat_conversation_observer_reads')
                        ->where('id', $row->id)
                        ->update([
                            'last_read_message_id' => $messageId,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('chat_conversation_participants', function (Blueprint $table): void {
            $table->dropIndex('chat_participant_read_message_idx');
            $table->dropColumn('last_read_message_id');
        });

        Schema::table('chat_conversation_observer_reads', function (Blueprint $table): void {
            $table->dropColumn('last_read_message_id');
        });
    }
};
