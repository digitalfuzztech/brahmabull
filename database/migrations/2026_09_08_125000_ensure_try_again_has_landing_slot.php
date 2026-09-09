<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $offerId = DB::table('spin_wheel_offers as offers')
            ->join('spin_wheel_offer_types as types', 'types.id', '=', 'offers.offer_type_id')
            ->where('types.action_type', 'try_again')
            ->where('types.is_active', true)
            ->where('offers.is_active', true)
            ->value('offers.id');

        if (! $offerId || DB::table('spin_wheel_assignments')->where('offer_id', $offerId)->where('is_active', true)->exists()) {
            return;
        }

        $assignments = DB::table('spin_wheel_assignments as assignments')
            ->join('spin_wheel_offers as offers', 'offers.id', '=', 'assignments.offer_id')
            ->join('spin_wheel_offer_types as types', 'types.id', '=', 'offers.offer_type_id')
            ->where('assignments.is_active', true)
            ->where('offers.is_active', true)
            ->where('types.is_active', true)
            ->where('types.action_type', '!=', 'try_again')
            ->get(['assignments.id', 'assignments.slot_type', 'types.action_type']);
        $counts = $assignments->countBy('action_type');
        $replacement = $assignments
            ->sortByDesc(fn ($assignment) => $assignment->slot_type === 'featured')
            ->first(fn ($assignment) => $counts[$assignment->action_type] > 1);

        if ($replacement) {
            DB::table('spin_wheel_assignments')->where('id', $replacement->id)->update([
                'offer_id' => $offerId,
                'display_label' => 'TRY AGAIN',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Historical results are immutable; the repaired live assignment is intentionally retained.
    }
};
