<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {

            $table->decimal('game_points_loaded', 12, 2)
                ->nullable()
                ->after('amount');

            $table->decimal('bonus_points_added', 12, 2)
                ->nullable()
                ->after('game_points_loaded');

        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {

            $table->dropColumn([
                'game_points_loaded',
                'bonus_points_added',
            ]);

        });
    }
};
