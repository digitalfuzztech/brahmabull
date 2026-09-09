<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_wheel_offer_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('action_type');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('spin_wheel_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_type_id')->constrained('spin_wheel_offer_types')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('display_value')->nullable();
            $table->string('category')->default('general');
            $table->unsignedInteger('rarity_weight')->default(1);
            $table->unsignedInteger('ranking_score')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('spin_wheel_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('wheel_number')->unique();
            $table->foreignId('offer_id')->constrained('spin_wheel_offers')->restrictOnDelete();
            $table->string('display_label')->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('spin_attempt_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->unsignedTinyInteger('attempts_granted');
            $table->unsignedTinyInteger('attempts_remaining');
            $table->timestamp('granted_at');
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['user_id', 'attempts_remaining']);
        });

        Schema::create('spin_wheel_spins', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_grant_id')->nullable()->constrained('spin_attempt_grants')->nullOnDelete();
            $table->unsignedInteger('spin_number');
            $table->unsignedTinyInteger('wheel_number');
            $table->foreignId('offer_id')->nullable()->constrained('spin_wheel_offers')->nullOnDelete();
            $table->string('offer_snapshot_name');
            $table->string('offer_snapshot_value')->nullable();
            $table->string('offer_snapshot_type');
            $table->string('offer_snapshot_category');
            $table->unsignedInteger('offer_snapshot_score')->default(0);
            $table->json('offer_snapshot_metadata')->nullable();
            $table->string('status')->default('awarded');
            $table->timestamp('spun_at');
            $table->timestamps();
            $table->unique(['user_id', 'spin_number']);
            $table->index(['spun_at', 'offer_snapshot_score']);
        });

        Schema::create('spin_reward_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spin_id')->unique()->constrained('spin_wheel_spins')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entitlement_type');
            $table->integer('numeric_value')->nullable();
            $table->string('code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('spin_reward_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spin_id')->unique()->constrained('spin_wheel_spins')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('spin_wheel_offers')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('staff_notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('spin_wheel_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('launcher_enabled')->default(true);
            $table->unsignedTinyInteger('maximum_stored_attempts')->default(3);
            $table->unsignedInteger('animation_duration_ms')->default(4800);
            $table->boolean('celebration_enabled')->default(true);
            $table->boolean('show_recent_win')->default(true);
            $table->text('terms_text')->nullable();
            $table->json('featured_labels')->nullable();
            $table->timestamps();
        });

        DB::table('spin_wheel_settings')->insert([
            'id' => 1,
            'terms_text' => 'Promotional rewards have no cash value and are not wagering funds.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_reward_claims');
        Schema::dropIfExists('spin_reward_entitlements');
        Schema::dropIfExists('spin_wheel_spins');
        Schema::dropIfExists('spin_attempt_grants');
        Schema::dropIfExists('spin_wheel_assignments');
        Schema::dropIfExists('spin_wheel_offers');
        Schema::dropIfExists('spin_wheel_offer_types');
        Schema::dropIfExists('spin_wheel_settings');
    }
};
