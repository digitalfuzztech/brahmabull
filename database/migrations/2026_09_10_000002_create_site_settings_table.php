<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 100);
            $table->string('logo_path')->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('x_url', 2048)->nullable();
            $table->string('youtube_url', 2048)->nullable();
            $table->string('discord_url', 2048)->nullable();
            $table->string('meta_title', 160)->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });

        DB::table('site_settings')->insert([
            'id' => 1,
            'site_name' => 'BrahmaBull Gaming Club',
            'logo_path' => null,
            'facebook_url' => config('brahmabull.social.facebook'),
            'instagram_url' => config('brahmabull.social.instagram'),
            'x_url' => config('brahmabull.social.x'),
            'youtube_url' => config('brahmabull.social.youtube'),
            'discord_url' => config('brahmabull.social.discord'),
            'meta_title' => 'BrahmaBull Member Portal',
            'meta_description' => 'BrahmaBull Member Platform',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
