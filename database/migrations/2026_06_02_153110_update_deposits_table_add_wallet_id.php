<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {

            $table->foreignId('wallet_id')
                ->nullable()
                ->after('game_id')
                ->constrained('wallets')
                ->nullOnDelete();

            // optional improvement (recommended)
            $table->string('payment_reference')->nullable()->after('amount');

            // optional tracking improvement
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('status');

            $table->timestamp('verified_at')->nullable()->after('verified_by');

        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {

            $table->dropForeign(['wallet_id']);
            $table->dropColumn('wallet_id');

            $table->dropForeign(['verified_by']);
            $table->dropColumn('verified_by');

            $table->dropColumn('payment_reference');
            $table->dropColumn('verified_at');
        });
    }
};
