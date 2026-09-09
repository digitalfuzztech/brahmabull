<?php

use App\Models\BrahmaDeposit;
use App\Models\Deposit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spin_wheel_assignments', function (Blueprint $table) {
            $table->string('slot_type', 16)->default('legacy')->after('wheel_number');
            $table->unsignedTinyInteger('slot_position')->nullable()->after('slot_type');
            $table->unique(['slot_type', 'slot_position'], 'spin_assignment_slot_unique');
        });

        Schema::table('spin_reward_entitlements', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('metadata')->index();
        });

        $this->backfillLatestVerifiedEvents();
    }

    public function down(): void
    {
        Schema::table('spin_reward_entitlements', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });

        Schema::table('spin_wheel_assignments', function (Blueprint $table) {
            $table->dropUnique('spin_assignment_slot_unique');
            $table->dropColumn(['slot_type', 'slot_position']);
        });
    }

    private function backfillLatestVerifiedEvents(): void
    {
        $playerIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'player')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('model_has_roles.model_id');

        if ($playerIds->isEmpty()) {
            return;
        }

        $events = collect();
        foreach ([[Deposit::class, 'deposits'], [BrahmaDeposit::class, 'brahma_deposits']] as [$sourceType, $table]) {
            DB::table($table)->whereIn('user_id', $playerIds)->where('status', 'verified')
                ->select('id', 'user_id', 'verified_at', 'updated_at')->get()
                ->each(fn ($row) => $events->push([
                    'source_type' => $sourceType,
                    'source_id' => $row->id,
                    'user_id' => $row->user_id,
                    'occurred_at' => $row->verified_at ?? $row->updated_at,
                ]));
        }

        foreach ($events->sortByDesc('occurred_at')->unique('user_id') as $event) {
            if (DB::table('spin_attempt_grants')->where('source_type', $event['source_type'])->where('source_id', $event['source_id'])->exists()) {
                continue;
            }
            $available = (int) DB::table('spin_attempt_grants')->where('user_id', $event['user_id'])->sum('attempts_remaining');
            $granted = max(0, 3 - min(3, $available));
            if ($granted === 0) {
                continue;
            }
            DB::table('spin_attempt_grants')->insert([
                'user_id' => $event['user_id'],
                'source_type' => $event['source_type'],
                'source_id' => $event['source_id'],
                'attempts_granted' => $granted,
                'attempts_remaining' => $granted,
                'granted_at' => $event['occurred_at'] ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
