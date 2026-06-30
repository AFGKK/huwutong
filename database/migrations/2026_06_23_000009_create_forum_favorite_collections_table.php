<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 收藏夹/分类
        Schema::create('forum_favorite_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 100);
            $table->string('icon', 10)->default('📁');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'sort_order']);
        });

        // 给 forum_favorites 添加收藏夹字段
        if (!Schema::hasColumn('forum_favorites', 'collection_id')) {
            Schema::table('forum_favorites', function (Blueprint $table) {
                $table->unsignedBigInteger('collection_id')->nullable()->after('post_id');
                $table->foreign('collection_id')->references('id')->on('forum_favorite_collections')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forum_favorites', 'collection_id')) {
            Schema::table('forum_favorites', function (Blueprint $table) {
                $table->dropForeign(['collection_id']);
                $table->dropColumn('collection_id');
            });
        }
        Schema::dropIfExists('forum_favorite_collections');
    }
};
