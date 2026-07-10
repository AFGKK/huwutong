<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forum_reactions')) { return; }
        Schema::create('forum_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            $table->string('reaction', 20); // like, love, laugh, amazed, sad, angry
            $table->timestamps();

            $table->unique(['post_id', 'user_id', 'reaction']);
            $table->foreign('post_id')->references('id')->on('forum_posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['post_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_reactions');
    }
};
