<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_rank_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('mode', ['automatic', 'manual'])->default('manual');
            $table->timestamps();
        });

        Schema::create('player_rank_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('period', ['last_7_days', 'this_month', 'all_time']);
            $table->unsignedTinyInteger('rank');
            $table->string('player_name')->nullable();
            $table->decimal('wins', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['period', 'rank']);
            $table->index(['period', 'rank']);
        });

        DB::table('player_rank_settings')->insert([
            'id' => 1,
            'mode' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('player_rank_entries');
        Schema::dropIfExists('player_rank_settings');
    }
};
