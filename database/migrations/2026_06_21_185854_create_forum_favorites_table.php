<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('forum_favorites')) {
            Schema::create('forum_favorites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('post_id');
                $table->timestamps();
                $table->unique(['user_id', 'post_id']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('post_id')->references('id')->on('forum_posts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_favorites');
    }
};
