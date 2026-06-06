<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashouts', function (Blueprint $table) {

            $table->string('payment_proof')
                ->nullable()
                ->after('qr_image');

            $table->unsignedBigInteger('original_verified_by')
                ->nullable()
                ->after('verified_by');

        });
    }

    public function down(): void
    {
        Schema::table('cashouts', function (Blueprint $table) {

            $table->dropColumn([
                'payment_proof',
                'original_verified_by'
            ]);

        });
    }
};
