<?php
/**
 * 互动数据迁移脚本
 * 将广场(OaArticleLike/OaFavorite/ForumLike)的数据迁移到统一 Like/Favorite 表
 * 
 * 运行: php migrate_interactions.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Like;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\OaArticleLike;
use App\Models\OaFavorite;
use App\Models\ForumLike;
use App\Models\ForumFollow;
use App\Models\OaFollower;
use Illuminate\Support\Facades\DB;

echo "开始迁移互动数据...\n\n";

// 1. OA 文章点赞 → Like 表
echo "1. 迁移 OA 文章点赞...\n";
$count = 0;
OaArticleLike::chunk(100, function ($likes) use (&$count) {
    foreach ($likes as $like) {
        try {
            Like::firstOrCreate([
                'user_id' => $like->user_id,
                'likeable_type' => 'App\Models\OaArticle',
                'likeable_id' => $like->article_id,
            ]);
            $count++;
        } catch (\Throwable $e) {
            echo "  跳过: {$e->getMessage()}\n";
        }
    }
});
if ($count > 0) {
    DB::table('oa_article_likes')->delete();
    echo "  已清理旧表 oa_article_likes\n";
}
echo "  完成: {$count} 条\n\n";

// 2. OA 文章收藏 → Favorite 表
echo "2. 迁移 OA 文章收藏...\n";
$count = 0;
OaFavorite::chunk(100, function ($favorites) use (&$count) {
    foreach ($favorites as $fav) {
        try {
            Favorite::firstOrCreate([
                'user_id' => $fav->user_id,
                'favorable_type' => 'App\Models\OaArticle',
                'favorable_id' => $fav->article_id,
            ]);
            $count++;
        } catch (\Throwable $e) {
            echo "  跳过: {$e->getMessage()}\n";
        }
    }
});
if ($count > 0) {
    DB::table('oa_favorites')->delete();
    echo "  已清理旧表 oa_favorites\n";
}
echo "  完成: {$count} 条\n\n";

// 3. 广场帖子点赞 → Like 表
echo "3. 迁移广场帖子点赞...\n";
$count = 0;
ForumLike::chunk(100, function ($likes) use (&$count) {
    foreach ($likes as $like) {
        try {
            Like::firstOrCreate([
                'user_id' => $like->user_id,
                'likeable_type' => 'App\Models\ForumPost',
                'likeable_id' => $like->likeable_id,
            ]);
            $count++;
        } catch (\Throwable $e) {
            echo "  跳过: {$e->getMessage()}\n";
        }
    }
});
if ($count > 0) {
    DB::table('forum_likes')->delete();
    echo "  已清理旧表 forum_likes\n";
}
echo "  完成: {$count} 条\n\n";

// 4. 博客旧格式点赞数据迁移 (blog_post_like/blog_post_fav)
echo "4. 迁移博客旧格式互动数据...\n";
$countLike = 0;
$countFav = 0;
$oldBlogLikes = DB::table('favorites')
    ->where('favorable_type', 'blog_post_like')
    ->get();
foreach ($oldBlogLikes as $old) {
    try {
        Like::firstOrCreate([
            'user_id' => $old->user_id,
            'likeable_type' => 'App\Models\BlogPost',
            'likeable_id' => $old->favorable_id,
        ]);
        $countLike++;
        DB::table('favorites')->where('id', $old->id)->delete();
    } catch (\Throwable $e) {}
}
$oldBlogFavs = DB::table('favorites')
    ->where('favorable_type', 'blog_post_fav')
    ->get();
foreach ($oldBlogFavs as $old) {
    try {
        Favorite::firstOrCreate([
            'user_id' => $old->user_id,
            'favorable_type' => 'App\Models\BlogPost',
            'favorable_id' => $old->favorable_id,
        ]);
        $countFav++;
        DB::table('favorites')->where('id', $old->id)->delete();
    } catch (\Throwable $e) {}
}
echo "  点赞迁移: {$countLike} 条\n";
echo "  收藏迁移: {$countFav} 条\n\n";

// 5. OA 关注 → Follow 表
echo "5. 迁移 OA 关注数据...\n";
$count = 0;
OaFollower::chunk(100, function ($followers) use (&$count) {
    foreach ($followers as $f) {
        try {
            Follow::firstOrCreate([
                'user_id' => $f->user_id,
                'followable_type' => 'App\Models\OfficialAccount',
                'followable_id' => $f->account_id,
            ]);
            $count++;
        } catch (\Throwable $e) {}
    }
});
if ($count > 0) {
    DB::table('official_account_followers')->delete();
    echo "  已清理旧表 official_account_followers\n";
}
echo "  完成: {$count} 条\n\n";

// 6. 广场用户关注 → Follow 表
echo "6. 迁移广场用户关注数据...\n";
$count = 0;
ForumFollow::chunk(100, function ($follows) use (&$count) {
    foreach ($follows as $f) {
        try {
            Follow::firstOrCreate([
                'user_id' => $f->user_id,
                'followable_type' => 'App\Models\User',
                'followable_id' => $f->target_user_id,
            ]);
            $count++;
        } catch (\Throwable $e) {}
    }
});
if ($count > 0) {
    DB::table('forum_follows')->delete();
    echo "  已清理旧表 forum_follows\n";
}
echo "  完成: {$count} 条\n\n";

echo "全部迁移完成!\n";
