<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->string('type', 20)->default('public');
            $table->unsignedBigInteger('created_by');
            $table->string('icon', 50)->default('#');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index('slug');
            $table->index('type');
        });

        Schema::create('channel_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20)->default('member');
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamps();
            $table->unique(['channel_id', 'user_id']);
        });

        Schema::create('channel_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->string('message_type', 20)->default('text');
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->softDeletes();
            $table->timestamps();
            $table->index('channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_messages');
        Schema::dropIfExists('channel_members');
        Schema::dropIfExists('channels');
        Schema::dropIfExists('channel_categories');
    }
};
