<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spin_promotional_point_ledgers', function (Blueprint $table) {
            $table->string('status')->default('awarded')->after('remaining_amount');
            $table->foreignId('processed_by')->nullable()->after('balance_after')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('processed_by');
            $table->string('fulfillment_source_type')->nullable()->after('processed_at');
            $table->unsignedBigInteger('fulfillment_source_id')->nullable()->after('fulfillment_source_type');
            $table->index(['user_id', 'point_type', 'status'], 'spin_points_user_type_status_index');
            $table->index(['fulfillment_source_type', 'fulfillment_source_id'], 'spin_points_fulfillment_source_index');
        });

        $badgeTypeId = DB::table('spin_wheel_offer_types')
            ->where('action_type', 'badge')->where('is_active', true)->value('id');
        if ($badgeTypeId && ! DB::table('spin_wheel_offers')->where('offer_type_id', $badgeTypeId)->where('is_active', true)->exists()) {
            $offerId = DB::table('spin_wheel_offers')->insertGetId([
                'offer_type_id' => $badgeTypeId,
                'name' => 'VIP Badge',
                'category' => 'badge',
                'rarity_weight' => 1,
                'ranking_score' => 10,
                'is_featured' => true,
                'notify_staff' => true,
                'is_active' => true,
                'metadata' => json_encode(['valid_days' => 3]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $visualSlots = [1 => 5, 2 => 10, 3 => 12, 4 => 14, 5 => 15, 6 => 16];
            foreach ($visualSlots as $position => $visualSlot) {
                if (! DB::table('spin_wheel_assignments')->where('slot_type', 'featured')->where('slot_position', $position)->exists()) {
                    DB::table('spin_wheel_assignments')->insert([
                        'wheel_number' => 100 + $visualSlot,
                        'slot_type' => 'featured',
                        'slot_position' => $position,
                        'offer_id' => $offerId,
                        'display_label' => 'VIP BADGE',
                        'weight' => 1,
                        'is_featured' => true,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('spin_promotional_point_ledgers', function (Blueprint $table) {
            $table->dropIndex('spin_points_user_type_status_index');
            $table->dropIndex('spin_points_fulfillment_source_index');
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['status', 'processed_at', 'fulfillment_source_type', 'fulfillment_source_id']);
        });
    }
};
