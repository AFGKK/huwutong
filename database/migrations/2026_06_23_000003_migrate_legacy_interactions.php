<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 迁移 OA 文章点赞 → likes 表
        DB::statement('INSERT IGNORE INTO likes (user_id, likeable_type, likeable_id, created_at, updated_at)
            SELECT user_id, "App\\\\Models\\\\OaArticle", article_id, created_at, updated_at
            FROM oa_article_likes');

        // 迁移广场点赞 → likes 表 (ForumLike 已是多态，直接映射)
        DB::statement('INSERT IGNORE INTO likes (user_id, likeable_type, likeable_id, created_at, updated_at)
            SELECT user_id, likeable_type, likeable_id, created_at, updated_at
            FROM forum_likes');

        // 迁移 OA 收藏 → favorites 表
        DB::statement('INSERT IGNORE INTO favorites (user_id, favorable_type, favorable_id, created_at, updated_at)
            SELECT user_id, "App\\\\Models\\\\OaArticle", article_id, created_at, updated_at
            FROM oa_favorites');

        // 迁移广场收藏 → favorites 表
        DB::statement('INSERT IGNORE INTO favorites (user_id, favorable_type, favorable_id, created_at, updated_at)
            SELECT f.user_id, "App\\\\Models\\\\ForumPost", f.post_id, f.created_at, f.updated_at
            FROM forum_favorites f');
    }

    public function down(): void
    {
        // 不反向迁移，保留旧表数据
    }
};
