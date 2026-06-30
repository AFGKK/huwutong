<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_conversations') || Schema::hasTable('conversation_polls')) {
            return;
        }

        Schema::create('conversation_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('user_conversations')->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('question', 200);
            $table->json('options');
            $table->string('type', 20)->default('single');
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('conversation_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('conversation_polls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('selected_options');
            $table->timestamps();
            $table->unique(['poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_poll_votes');
        Schema::dropIfExists('conversation_polls');
    }
};
