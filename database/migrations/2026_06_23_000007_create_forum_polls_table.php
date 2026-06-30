<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_polls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('question', 500);
            $table->boolean('is_multiple')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('post_id')->references('id')->on('forum_posts')->onDelete('cascade');
        });

        Schema::create('forum_poll_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->string('label', 200);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('poll_id')->references('id')->on('forum_polls')->onDelete('cascade');
        });

        Schema::create('forum_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['option_id', 'user_id']);
            $table->foreign('poll_id')->references('id')->on('forum_polls')->onDelete('cascade');
            $table->foreign('option_id')->references('id')->on('forum_poll_options')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_poll_votes');
        Schema::dropIfExists('forum_poll_options');
        Schema::dropIfExists('forum_polls');
    }
};
