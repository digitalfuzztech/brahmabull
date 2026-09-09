<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spin_wheel_spins', function (Blueprint $table) {
            $table->unsignedTinyInteger('wheel_slot')->nullable()->after('wheel_number')->index();
        });

        $mapping = [
            'informational' => 'promotional_perk',
            'coupon' => 'promotional_perk',
            'access_perk' => 'promotional_perk',
            'non_wagering_discount' => 'promotional_perk',
            'manual_claim' => 'manual_reward',
            'merchandise' => 'manual_reward',
        ];
        foreach ($mapping as $old => $new) {
            DB::table('spin_wheel_offer_types')->where('action_type', $old)->update(['action_type' => $new]);
        }

        $assignments = DB::table('spin_wheel_assignments')->where('slot_type', 'legacy')->where('is_active', true)->orderByDesc('is_featured')->orderBy('id')->get();
        $numeric = 1;
        $featured = 1;
        foreach ($assignments as $assignment) {
            $type = $assignment->is_featured && $featured <= 6 ? 'featured' : 'numeric';
            $position = $type === 'featured' ? $featured++ : $numeric++;
            if (($type === 'numeric' && $position > 10) || ($type === 'featured' && $position > 6)) {
                continue;
            }
            $visual = $this->visualSlot($type, $position);
            DB::table('spin_wheel_assignments')->insert([
                'wheel_number' => 100 + $visual,
                'slot_type' => $type,
                'slot_position' => $position,
                'offer_id' => $assignment->offer_id,
                'display_label' => $assignment->display_label,
                'weight' => $assignment->weight,
                'is_featured' => $type === 'featured',
                'is_active' => $assignment->is_active,
                'starts_at' => $assignment->starts_at,
                'ends_at' => $assignment->ends_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('spin_wheel_spins', function (Blueprint $table) {
            $table->dropIndex(['wheel_slot']);
            $table->dropColumn('wheel_slot');
        });
    }

    private function visualSlot(string $type, int $position): int
    {
        $order = [['numeric', 1], ['numeric', 5], ['numeric', 6], ['numeric', 3], ['featured', 1], ['numeric', 2], ['numeric', 4], ['numeric', 9], ['numeric', 10], ['featured', 2], ['numeric', 8], ['featured', 3], ['numeric', 7], ['featured', 4], ['featured', 5], ['featured', 6]];
        foreach ($order as $index => $slot) {
            if ($slot === [$type, $position]) {
                return $index + 1;
            }
        }

        return 1;
    }
};
