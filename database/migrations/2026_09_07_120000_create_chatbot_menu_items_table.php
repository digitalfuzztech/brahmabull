<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('action_type', 64);
            $table->text('response_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        DB::table('chatbot_menu_items')->insert([
            ['label' => 'My Play Request', 'action_type' => 'show_pending_plays', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'My Deposit', 'action_type' => 'show_pending_deposits', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'My Cashout', 'action_type' => 'show_pending_cashouts', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Brahma Balance', 'action_type' => 'show_brahma_balance', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Talk to Support', 'action_type' => 'request_human', 'sort_order' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Other', 'action_type' => 'none', 'sort_order' => 60, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_menu_items');
    }
};
