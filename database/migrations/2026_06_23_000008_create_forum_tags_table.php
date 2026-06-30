<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 60)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('forum_post_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();
            $table->unique(['post_id', 'tag_id']);
            $table->foreign('post_id')->references('id')->on('forum_posts')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('forum_tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_tag');
        Schema::dropIfExists('forum_tags');
    }
};
