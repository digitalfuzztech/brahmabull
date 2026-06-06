<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {

            $table->id();

            /*
             * Recipient
             */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * Notification type
             */
            $table->string('type');

            /*
             * Display
             */
            $table->string('title');

            $table->text('message');

            /*
             * Button
             */
            $table->string('action_text')
                ->nullable();

            $table->string('action_url')
                ->nullable();

            /*
             * Related model
             */
            $table->string('entity_type')
                ->nullable();

            $table->unsignedBigInteger('entity_id')
                ->nullable();

            /*
             * Optional game relation
             */
            $table->foreignId('game_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             * Read state
             */
            $table->boolean('is_read')
                ->default(false);

            /*
             * Actor
             */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('type');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
