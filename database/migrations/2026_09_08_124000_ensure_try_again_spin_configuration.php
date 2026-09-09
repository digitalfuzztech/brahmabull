<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $typeId = DB::table('spin_wheel_offer_types')
            ->where('action_type', 'try_again')->where('is_active', true)->value('id');
        if (! $typeId) {
            $slug = 'try-again';
            if (DB::table('spin_wheel_offer_types')->where('slug', $slug)->exists()) {
                $slug = 'try-again-spin';
            }
            $typeId = DB::table('spin_wheel_offer_types')->insertGetId([
                'name' => 'Try Again', 'slug' => $slug, 'action_type' => 'try_again', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $offerId = DB::table('spin_wheel_offers')
            ->where('offer_type_id', $typeId)->where('is_active', true)->value('id');
        if (! $offerId) {
            $offerId = DB::table('spin_wheel_offers')->insertGetId([
                'offer_type_id' => $typeId, 'name' => 'Try Again', 'category' => 'try_again',
                'rarity_weight' => 1, 'ranking_score' => 0, 'is_featured' => true,
                'notify_staff' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (DB::table('spin_wheel_assignments')->where('offer_id', $offerId)->where('is_active', true)->exists()) {
            return;
        }

        $visualSlots = [
            ['featured', 1, 5], ['featured', 2, 10], ['featured', 3, 12],
            ['featured', 4, 14], ['featured', 5, 15], ['featured', 6, 16],
            ['numeric', 1, 1], ['numeric', 5, 2], ['numeric', 6, 3], ['numeric', 3, 4],
            ['numeric', 2, 6], ['numeric', 4, 7], ['numeric', 9, 8], ['numeric', 10, 9],
            ['numeric', 8, 11], ['numeric', 7, 13],
        ];
        foreach ($visualSlots as [$type, $position, $visualSlot]) {
            if (! DB::table('spin_wheel_assignments')->where('slot_type', $type)->where('slot_position', $position)->exists()) {
                DB::table('spin_wheel_assignments')->insert([
                    'wheel_number' => 100 + $visualSlot, 'slot_type' => $type, 'slot_position' => $position,
                    'offer_id' => $offerId, 'display_label' => $type === 'featured' ? 'TRY AGAIN' : null,
                    'weight' => 1, 'is_featured' => $type === 'featured', 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                break;
            }
        }
    }

    public function down(): void
    {
        // Promotional configuration is retained to preserve any resulting spin history.
    }
};
