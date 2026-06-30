<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('icon', 10)->default('📋');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('title', 200);
            $table->text('content');
            $table->integer('views_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->integer('replies_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'created_at']);
        });

        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('post_id');
        });

        Schema::create('forum_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->morphs('likeable');
            $table->timestamps();
            $table->unique(['user_id', 'likeable_id', 'likeable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_likes');
        Schema::dropIfExists('forum_replies');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_categories');
    }
};
