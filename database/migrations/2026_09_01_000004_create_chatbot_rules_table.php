<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('trigger_type', ['keyword', 'exact', 'option', 'fallback']);
            $table->json('trigger_value')->nullable();
            $table->text('response_text')->nullable();
            $table->string('action_type')->nullable();
            $table->json('action_config')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'trigger_type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_rules');
    }
};
