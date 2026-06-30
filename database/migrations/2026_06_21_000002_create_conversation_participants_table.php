<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role')->nullable()->default('member')->comment('owner,admin,member');
                $table->integer('unread_count')->default(0);
                $table->timestamp('last_read_at')->nullable();
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_muted')->default(false);
                $table->timestamp('deleted_at')->nullable();
                $table->timestamp('slow_mode_until')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->boolean('is_hidden')->default(false);
                $table->timestamp('hidden_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
                $table->foreign('conversation_id')->references('id')->on('user_conversations')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
