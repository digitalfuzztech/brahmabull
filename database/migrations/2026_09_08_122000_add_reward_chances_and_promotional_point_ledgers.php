<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spin_wheel_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('try_again_chance')->default(50);
            $table->unsignedTinyInteger('free_spin_chance')->default(20);
            $table->unsignedTinyInteger('bonus_points_chance')->default(10);
            $table->unsignedTinyInteger('sajilo_points_chance')->default(10);
            $table->unsignedTinyInteger('badge_chance')->default(10);
        });

        Schema::table('spin_wheel_offers', function (Blueprint $table) {
            $table->boolean('notify_staff')->default(false)->after('is_featured');
        });

        Schema::create('spin_promotional_point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spin_id')->unique()->constrained('spin_wheel_spins')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('point_type');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('remaining_amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['user_id', 'point_type']);
        });

        DB::table('spin_wheel_offer_types')->where('slug', 'sajilo-points')->update(['action_type' => 'sajilo_points']);
        DB::table('spin_wheel_offer_types')->where('slug', 'bonus-points')->update(['action_type' => 'bonus_points']);
        DB::table('spin_wheel_offer_types')->where('slug', 'try-again')->update(['action_type' => 'try_again']);
        DB::table('spin_wheel_offer_types')->whereNotIn('action_type', [
            'try_again', 'free_spin', 'bonus_points', 'sajilo_points', 'badge',
        ])->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_promotional_point_ledgers');
        Schema::table('spin_wheel_offers', fn (Blueprint $table) => $table->dropColumn('notify_staff'));
        Schema::table('spin_wheel_settings', function (Blueprint $table) {
            $table->dropColumn(['try_again_chance', 'free_spin_chance', 'bonus_points_chance', 'sajilo_points_chance', 'badge_chance']);
        });
    }
};
