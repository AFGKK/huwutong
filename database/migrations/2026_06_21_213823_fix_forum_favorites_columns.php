<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_favorites', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_favorites', 'user_id')) {
                $table->unsignedBigInteger('user_id')->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('forum_favorites', 'post_id')) {
                $table->unsignedBigInteger('post_id')->after('user_id');
                $table->foreign('post_id')->references('id')->on('forum_posts')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
    }
};
