<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('user_conversations')->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('question', 500);
            $table->string('type', 20)->default('single')->comment('single/multiple/ranked');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_hide_results')->default(false);
            $table->unsignedInteger('max_choices')->default(1)->comment('多选时最大可选数');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'is_closed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
