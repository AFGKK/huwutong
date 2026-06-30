<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\OaFollower;
use App\Models\OfficialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserInteractionController extends Controller
{
    /**
     * 统一获取用户所有互动数据
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tab = $request->input('tab', 'all'); // all | follows | favorites | likes
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);
        $result = [];

        // 关注的内容 (OfficialAccount / User)
        if (in_array($tab, ['all', 'follows'])) {
            $follows = \App\Models\Follow::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($f) {
                    $item = [
                        'type' => $f->followable_type === 'App\\Models\\User' ? 'user' : 'official_account',
                        'id' => $f->followable_id,
                        'interacted_at' => $f->created_at,
                    ];
                    try {
                        $model = $f->followable_type::find($f->followable_id);
                        if ($model) {
                            $item['name'] = $model->name ?? $model->title ?? '';
                            $item['slug'] = $model->slug ?? '';
                            $item['avatar'] = $model->avatar ?? '';
                            $item['description'] = $model->description ?? '';
                            $item['followers_count'] = \App\Models\Follow::where('followable_type', $f->followable_type)
                                ->where('followable_id', $f->followable_id)->count();
                        }
                    } catch (\Throwable $e) {
                        $item['name'] = '(已删除)';
                    }
                    return $item;
                });
            $result['follows'] = $follows;
        }

        // 收藏的内容 (BlogPost, OaArticle, ForumPost)
        if (in_array($tab, ['all', 'favorites'])) {
            $search = $request->input('search', '');
            $typeFilter = $request->input('type_filter', '');

            $query = Favorite::where('user_id', $user->id);

            if ($typeFilter) {
                $resolvedType = $this->resolveModelClass($typeFilter);
                $query->where('favorable_type', $resolvedType);
            }

            $favorites = $query->orderByDesc('created_at')
                ->get()
                ->map(function ($fav) {
                    $item = [
                        'type' => $this->typeLabel($fav->favorable_type),
                        'model_type' => $fav->favorable_type,
                        'id' => $fav->favorable_id,
                        'interacted_at' => $fav->created_at,
                    ];
                    try {
                        $model = $fav->favorable_type::find($fav->favorable_id);
                        if ($model) {
                            $item['title'] = $model->title ?? $model->name ?? '';
                            $item['excerpt'] = $model->excerpt ?? $model->summary ?? '';
                            $item['cover'] = $model->cover_image ?? $model->featured_image ?? $model->image ?? '';
                            $item['slug'] = $model->slug ?? '';
                            $item['url'] = $this->itemUrl($fav->favorable_type, $model);
                        }
                    } catch (\Throwable $e) {
                        $item['title'] = '(已删除)';
                    }
                    return $item;
                });

            // 搜索过滤（按标题关键词）
            if ($search) {
                $favorites = $favorites->filter(fn($item) => mb_stripos($item['title'] ?? '', $search) !== false);
            }

            $result['favorites'] = $favorites->values();
        }

        // 点赞的内容 (BlogPost, OaArticle, ForumPost)
        if (in_array($tab, ['all', 'likes'])) {
            $likes = Like::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($like) {
                    $item = [
                        'type' => $this->typeLabel($like->likeable_type),
                        'model_type' => $like->likeable_type,
                        'id' => $like->likeable_id,
                        'interacted_at' => $like->created_at,
                    ];
                    try {
                        $model = $like->likeable_type::find($like->likeable_id);
                        if ($model) {
                            $item['title'] = $model->title ?? $model->name ?? '';
                            $item['excerpt'] = $model->excerpt ?? $model->summary ?? '';
                            $item['cover'] = $model->cover_image ?? $model->featured_image ?? $model->image ?? '';
                            $item['slug'] = $model->slug ?? '';
                            $item['url'] = $this->itemUrl($like->likeable_type, $model);
                        }
                    } catch (\Throwable $e) {
                        $item['title'] = '(已删除)';
                    }
                    return $item;
                });
            $result['likes'] = $likes;
        }

        return ApiResponse::success($result);
    }

    /**
     * 添加收藏
     */
    public function addFavorite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',  // BlogPost, OaArticle, ForumPost
            'id' => 'required|integer',
        ]);

        $type = $this->resolveModelClass($validated['type']);
        $user = $request->user();

        if (Favorite::where('user_id', $user->id)
            ->where('favorable_type', $type)
            ->where('favorable_id', $validated['id'])
            ->exists()
        ) {
            return ApiResponse::error('ALREADY_FAVORITED', '已收藏');
        }

        Favorite::create([
            'user_id' => $user->id,
            'favorable_type' => $type,
            'favorable_id' => $validated['id'],
        ]);

        return ApiResponse::success(null, '收藏成功');
    }

    /**
     * 取消收藏
     */
    public function removeFavorite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'id' => 'required|integer',
        ]);

        $type = $this->resolveModelClass($validated['type']);
        $user = $request->user();

        Favorite::where('user_id', $user->id)
            ->where('favorable_type', $type)
            ->where('favorable_id', $validated['id'])
            ->delete();

        return ApiResponse::success(null, '已取消收藏');
    }

    /**
     * 添加点赞
     */
    public function addLike(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'id' => 'required|integer',
        ]);

        $type = $this->resolveModelClass($validated['type']);
        $user = $request->user();

        if (Like::where('user_id', $user->id)
            ->where('likeable_type', $type)
            ->where('likeable_id', $validated['id'])
            ->exists()
        ) {
            return ApiResponse::error('ALREADY_LIKED', '已点赞');
        }

        Like::create([
            'user_id' => $user->id,
            'likeable_type' => $type,
            'likeable_id' => $validated['id'],
        ]);

        return ApiResponse::success(null, '点赞成功');
    }

    /**
     * 取消点赞
     */
    public function removeLike(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'id' => 'required|integer',
        ]);

        $type = $this->resolveModelClass($validated['type']);
        $user = $request->user();

        Like::where('user_id', $user->id)
            ->where('likeable_type', $type)
            ->where('likeable_id', $validated['id'])
            ->delete();

        return ApiResponse::success(null, '已取消点赞');
    }

    /**
     * 检查用户对某内容的互动状态
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'id' => 'required|integer',
        ]);

        $type = $this->resolveModelClass($validated['type']);
        $user = $request->user();

        return ApiResponse::success([
            'is_favorited' => Favorite::where('user_id', $user->id)
                ->where('favorable_type', $type)
                ->where('favorable_id', $validated['id'])->exists(),
            'is_liked' => Like::where('user_id', $user->id)
                ->where('likeable_type', $type)
                ->where('likeable_id', $validated['id'])->exists(),
        ]);
    }

    // ─── 辅助方法 ───

    /**
     * 阅读统计仪表盘
     */
    public function readingStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();
        $today = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();

        // Blog 阅读统计
        $blogReads = \App\Models\BlogRead::where('user_id', $user->id);
        $blogToday = (clone $blogReads)->where('created_at', '>=', $today)->count();
        $blogTotal = (clone $blogReads)->count();
        $blogWeekly = (clone $blogReads)->where('created_at', '>=', $weekStart)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        // OA 文章阅读统计
        $oaReads = \App\Models\OaArticleRead::where('user_id', $user->id);
        $oaToday = (clone $oaReads)->where('created_at', '>=', $today)->count();
        $oaTotal = (clone $oaReads)->count();
        $oaWeekly = (clone $oaReads)->where('created_at', '>=', $weekStart)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $totalRead = $blogTotal + $oaTotal;
        $totalToday = $blogToday + $oaToday;

        // 每周趋势（7天）
        $weeklyTrend = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $weeklyTrend[] = [
                'date' => $key,
                'label' => ['一','二','三','四','五','六','日'][$i],
                'blog' => (int)($blogWeekly[$key] ?? 0),
                'oa' => (int)($oaWeekly[$key] ?? 0),
                'total' => (int)($blogWeekly[$key] ?? 0) + (int)($oaWeekly[$key] ?? 0),
            ];
        }

        // 连续阅读天数
        $streak = 0;
        $checkDate = $today->copy();
        while (true) {
            $blogCount = (clone $blogReads)->where('created_at', '>=', $checkDate)->where('created_at', '<', $checkDate->copy()->addDay())->count();
            $oaCount = (clone $oaReads)->where('created_at', '>=', $checkDate)->where('created_at', '<', $checkDate->copy()->addDay())->count();
            if ($blogCount + $oaCount > 0) {
                $streak++;
                $checkDate->subDay();
            } else {
                break;
            }
        }

        // 估算阅读时间（每分钟约读 1 篇）
        $totalMinutes = $totalRead;

        // 成就
        $achievements = [];
        if ($totalRead >= 1) $achievements[] = ['key' => 'first_read', 'name' => '初次阅读', 'icon' => '📖', 'unlocked' => true];
        if ($totalRead >= 10) $achievements[] = ['key' => 'read_10', 'name' => '阅读 10 篇', 'icon' => '📚', 'unlocked' => true];
        if ($totalRead >= 50) $achievements[] = ['key' => 'read_50', 'name' => '阅读 50 篇', 'icon' => '📚', 'unlocked' => true];
        if ($totalRead >= 100) $achievements[] = ['key' => 'read_100', 'name' => '百篇达人', 'icon' => '🏆', 'unlocked' => true];
        if ($streak >= 3) $achievements[] = ['key' => 'streak_3', 'name' => '坚持 3 天', 'icon' => '🔥', 'unlocked' => true];
        if ($streak >= 7) $achievements[] = ['key' => 'streak_7', 'name' => '坚持 7 天', 'icon' => '🔥', 'unlocked' => true];
        if ($streak >= 30) $achievements[] = ['key' => 'streak_30', 'name' => '月度之星', 'icon' => '⭐', 'unlocked' => true];
        if ($blogTotal >= 1 && $oaTotal >= 1) $achievements[] = ['key' => 'both_reader', 'name' => '跨平台读者', 'icon' => '🌐', 'unlocked' => true];

        // 添加未解锁成就预览
        $allAchievements = [
            ['key' => 'first_read', 'name' => '初次阅读', 'icon' => '📖', 'need' => 1],
            ['key' => 'read_10', 'name' => '阅读 10 篇', 'icon' => '📚', 'need' => 10],
            ['key' => 'read_50', 'name' => '阅读 50 篇', 'icon' => '📚', 'need' => 50],
            ['key' => 'read_100', 'name' => '百篇达人', 'icon' => '🏆', 'need' => 100],
            ['key' => 'streak_3', 'name' => '坚持 3 天', 'icon' => '🔥', 'need' => 3],
            ['key' => 'streak_7', 'name' => '坚持 7 天', 'icon' => '🔥', 'need' => 7],
            ['key' => 'streak_30', 'name' => '月度之星', 'icon' => '⭐', 'need' => 30],
            ['key' => 'both_reader', 'name' => '跨平台读者', 'icon' => '🌐', 'need' => '双平台'],
        ];

        // 阅读目标（从偏好设置读取）
        $dailyGoal = (int)($user->preferences['reading_daily_goal'] ?? 3);
        $goalProgress = $dailyGoal > 0 ? min(100, round(($totalToday / $dailyGoal) * 100)) : 0;

        return ApiResponse::success([
            'total_read' => $totalRead,
            'today_read' => $totalToday,
            'total_minutes' => $totalMinutes,
            'streak_days' => $streak,
            'blog_read' => $blogTotal,
            'oa_read' => $oaTotal,
            'daily_goal' => $dailyGoal,
            'goal_progress' => $goalProgress,
            'weekly_trend' => $weeklyTrend,
            'achievements' => $allAchievements,
            'unlocked_achievements' => $achievements,
        ]);
    }

    /**
     * 保存阅读目标
     */
    public function saveReadingGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'daily_goal' => 'required|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $preferences['reading_daily_goal'] = (int)$validated['daily_goal'];
        $user->preferences = $preferences;
        $user->save();

        return ApiResponse::success([
            'daily_goal' => (int)$validated['daily_goal'],
        ], '阅读目标已更新');
    }

    /**
     * 互动热力图 — 年度每日活动数据
     */
    public function heatmap(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = (int)$request->input('year', now()->year);
        $start = now()->setYear($year)->startOfYear();
        $end = now()->setYear($year)->endOfYear();

        // Blog 阅读
        $blogDaily = \App\Models\BlogRead::where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        // OA 阅读
        $oaDaily = \App\Models\OaArticleRead::where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        // 收藏
        $favDaily = Favorite::where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        // 点赞
        $likeDaily = Like::where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        // 构建全年数据
        $days = [];
        $maxCount = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $count = (int)($blogDaily[$key] ?? 0) + (int)($oaDaily[$key] ?? 0)
                   + (int)($favDaily[$key] ?? 0) + (int)($likeDaily[$key] ?? 0);
            if ($count > $maxCount) $maxCount = $count;
            $days[] = [
                'date' => $key,
                'count' => $count,
                'level' => $count === 0 ? 0 : ($count <= 1 ? 1 : ($count <= 3 ? 2 : ($count <= 6 ? 3 : 4))),
            ];
            $cursor->addDay();
        }

        // 今日统计
        $today = now()->format('Y-m-d');
        $todayTotal = 0;
        foreach ($days as $d) {
            if ($d['date'] === $today) { $todayTotal = $d['count']; break; }
        }

        // 总活跃天数
        $activeDays = count(array_filter($days, fn($d) => $d['count'] > 0));

        return ApiResponse::success([
            'year' => $year,
            'days' => $days,
            'max_count' => $maxCount,
            'total_active_days' => $activeDays,
            'today_count' => $todayTotal,
            'total_interactions' => array_sum(array_column($days, 'count')),
        ]);
    }

    /**
     * 猜你喜欢 — 基于阅读偏好推荐内容
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int)$request->input('limit', 6);

        // 收集用户感兴趣的标签
        $interestTags = [];

        // 从收藏内容提取标签
        $favorites = Favorite::where('user_id', $user->id)->get();
        foreach ($favorites as $fav) {
            try {
                $model = $fav->favorable_type::find($fav->favorable_id);
                if ($model && $model->tags) {
                    foreach ((array)$model->tags as $tag) {
                        $t = mb_strtolower(trim($tag));
                        if ($t) $interestTags[$t] = ($interestTags[$t] ?? 0) + 2;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 从点赞内容提取标签
        $likes = Like::where('user_id', $user->id)->get();
        foreach ($likes as $like) {
            try {
                $model = $like->likeable_type::find($like->likeable_id);
                if ($model && $model->tags) {
                    foreach ((array)$model->tags as $tag) {
                        $t = mb_strtolower(trim($tag));
                        if ($t) $interestTags[$t] = ($interestTags[$t] ?? 0) + 1;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 按兴趣权重排序
        arsort($interestTags);
        $topInterestTags = array_keys(array_slice($interestTags, 0, 5));

        // 已互动的 ID 集合（排除已看过的）
        $readBlogIds = \App\Models\BlogRead::where('user_id', $user->id)->pluck('blog_id')->toArray();
        $favoriteBlogIds = Favorite::where('user_id', $user->id)
            ->where('favorable_type', 'App\Models\BlogPost')->pluck('favorable_id')->toArray();
        $likeBlogIds = Like::where('user_id', $user->id)
            ->where('likeable_type', 'App\Models\BlogPost')->pluck('likeable_id')->toArray();
        $excludeBlogIds = array_unique(array_merge($readBlogIds, $favoriteBlogIds, $likeBlogIds));

        $results = [];

        // 1. 推荐 Blog 文章
        if (!empty($topInterestTags)) {
            $blogQuery = \App\Models\BlogPost::published()
                ->whereNotIn('id', $excludeBlogIds)
                ->where(function ($q) use ($topInterestTags) {
                    foreach ($topInterestTags as $tag) {
                        $q->orWhere('tags', 'like', '%"' . $tag . '"%')
                          ->orWhere('tags', 'like', '%' . $tag . '%');
                    }
                })
                ->select('id', 'title', 'slug', 'excerpt', 'featured_image', 'tags', 'published_at', 'type', 'created_at')
                ->orderByDesc('published_at')
                ->limit($limit);

            foreach ($blogQuery->get() as $post) {
                $results[] = [
                    'type' => 'blog_post',
                    'id' => $post->id,
                    'title' => $post->title,
                    'excerpt' => $post->excerpt,
                    'cover' => $post->featured_image,
                    'slug' => $post->slug,
                    'tags' => $post->tags,
                    'published_at' => $post->published_at?->toDateTimeString(),
                    'source' => 'blog',
                    'reason' => $this->matchReason($post->tags ?? [], $topInterestTags),
                ];
            }
        }

        // 2. 如果没有标签匹配或者结果不够，补充最新文章
        if (count($results) < $limit) {
            $remaining = $limit - count($results);
            $existingIds = array_column($results, 'id');
            $fallback = \App\Models\BlogPost::published()
                ->whereNotIn('id', array_merge($excludeBlogIds, $existingIds))
                ->select('id', 'title', 'slug', 'excerpt', 'featured_image', 'tags', 'published_at', 'type', 'created_at')
                ->orderByDesc('published_at')
                ->limit($remaining)
                ->get();

            foreach ($fallback as $post) {
                $results[] = [
                    'type' => 'blog_post',
                    'id' => $post->id,
                    'title' => $post->title,
                    'excerpt' => $post->excerpt,
                    'cover' => $post->featured_image,
                    'slug' => $post->slug,
                    'tags' => $post->tags,
                    'published_at' => $post->published_at?->toDateTimeString(),
                    'source' => 'blog',
                    'reason' => '最新推荐',
                ];
            }
        }

        // 随机打乱
        shuffle($results);

        return ApiResponse::success([
            'items' => array_slice($results, 0, $limit),
            'total' => count($results),
            'interest_tags' => $topInterestTags,
        ]);
    }

    /**
     * 匹配推荐理由
     */
    protected function matchReason(array $tags, array $interestTags): string
    {
        foreach ($interestTags as $it) {
            foreach ($tags as $t) {
                if (mb_stripos((string)$t, $it) !== false || mb_stripos($it, (string)$t) !== false) {
                    return '因您关注「' . $it . '」';
                }
            }
        }
        return '为您推荐';
    }

    public function readingReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', 'monthly'); // weekly | monthly

        $now = now();
        $currentStart = $period === 'weekly' ? $now->copy()->startOfWeek() : $now->copy()->startOfMonth();
        $currentEnd = $period === 'weekly' ? $now->copy()->endOfWeek() : $now->copy()->endOfMonth();
        $prevStart = $period === 'weekly' ? $currentStart->copy()->subWeek()->startOfWeek() : $currentStart->copy()->subMonth()->startOfMonth();
        $prevEnd = $period === 'weekly' ? $currentStart->copy()->subDay()->endOfWeek() : $currentStart->copy()->subDay()->endOfMonth();

        // 当前周期数据
        $blogCurrent = \App\Models\BlogRead::where('user_id', $user->id)
            ->whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $oaCurrent = \App\Models\OaArticleRead::where('user_id', $user->id)
            ->whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $favCurrent = Favorite::where('user_id', $user->id)
            ->whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $likeCurrent = Like::where('user_id', $user->id)
            ->whereBetween('created_at', [$currentStart, $currentEnd])->count();

        // 上一周期数据
        $blogPrev = \App\Models\BlogRead::where('user_id', $user->id)
            ->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $oaPrev = \App\Models\OaArticleRead::where('user_id', $user->id)
            ->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $currentRead = $blogCurrent + $oaCurrent;
        $prevRead = $blogPrev + $oaPrev;
        $growth = $prevRead > 0 ? round((($currentRead - $prevRead) / $prevRead) * 100) : ($currentRead > 0 ? 100 : 0);

        // 最爱时段
        $hourly = \App\Models\BlogRead::where('user_id', $user->id)
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->pluck('count', 'hour');

        foreach (range(0, 23) as $h) {
            if (!isset($hourly[$h])) $hourly[$h] = 0;
        }
        $peakHour = $hourly->search($hourly->max());

        // 最爱标签
        $tags = [];
        $favItems = Favorite::where('user_id', $user->id)
            ->whereBetween('created_at', [$currentStart, $currentEnd])->get();
        foreach ($favItems as $fav) {
            try {
                $model = $fav->favorable_type::find($fav->favorable_id);
                if ($model && $model->tags) {
                    foreach ((array)$model->tags as $tag) {
                        $t = trim($tag);
                        if ($t) $tags[$t] = ($tags[$t] ?? 0) + 1;
                    }
                }
            } catch (\Throwable $e) {}
        }
        arsort($tags);
        $topTags = array_slice(array_keys($tags), 0, 3);

        // 连续阅读
        $streak = 0;
        $check = $currentEnd->copy();
        while (true) {
            $bc = \App\Models\BlogRead::where('user_id', $user->id)
                ->whereDate('created_at', $check->format('Y-m-d'))->exists();
            $oc = \App\Models\OaArticleRead::where('user_id', $user->id)
                ->whereDate('created_at', $check->format('Y-m-d'))->exists();
            if ($bc || $oc) { $streak++; $check->subDay(); }
            else break;
        }

        // 每日平均
        $daysInPeriod = $currentStart->diffInDays($currentEnd) + 1;
        $avgDaily = $daysInPeriod > 0 ? round($currentRead / $daysInPeriod, 1) : 0;

        $periodLabel = $period === 'weekly' ? '本周' : '本月';
        $prevPeriodLabel = $period === 'weekly' ? '上周' : '上月';

        return ApiResponse::success([
            'period' => $period,
            'period_label' => $periodLabel,
            'date_range' => $currentStart->format('Y-m-d') . ' ~ ' . $currentEnd->format('Y-m-d'),
            'total_read' => $currentRead,
            'blog_read' => $blogCurrent,
            'oa_read' => $oaCurrent,
            'total_favorites' => $favCurrent,
            'total_likes' => $likeCurrent,
            'growth_percent' => $growth,
            'growth_label' => $growth >= 0 ? "+{$growth}%" : "{$growth}%",
            'avg_daily' => $avgDaily,
            'total_minutes' => $currentRead, // 估算
            'streak_days' => $streak,
            'peak_hour' => $peakHour,
            'top_tags' => $topTags,
            'prev_read' => $prevRead,
            'prev_period_label' => $prevPeriodLabel,
            'user_name' => $user->name,
            'user_avatar' => $user->avatar_url ?? $user->avatar ?? '',
        ]);
    }

    protected function typeLabel(string $class): string
    {
        return match ($class) {
            'App\Models\BlogPost' => 'blog_post',
            'App\Models\OaArticle' => 'oa_article',
            'App\Models\ForumPost' => 'forum_post',
            'App\Models\KbArticle' => 'kb_article',
            default => 'unknown',
        };
    }

    protected function resolveModelClass(string $short): string
    {
        return match ($short) {
            'blog_post', 'BlogPost' => 'App\Models\BlogPost',
            'oa_article', 'OaArticle' => 'App\Models\OaArticle',
            'forum_post', 'ForumPost' => 'App\Models\ForumPost',
            'kb_article', 'KbArticle' => 'App\Models\KbArticle',
            default => $short,
        };
    }

    protected function itemUrl(string $type, $model): string
    {
        return match ($type) {
            'App\Models\BlogPost' => '/blog/' . ($model->slug ?? ''),
            'App\Models\OaArticle' => '/build/user-chat', // OA 文章在 IM 内查看
            'App\Models\ForumPost' => '/build/user-chat', // 广场在 IM 内查看
            'App\Models\KbArticle' => '/help?article=' . ($model->id ?? ''),
            default => '',
        };
    }

    /**
     * 关注动态 Feed — 用户关注的 OA 账号的最新发布内容
     */
    public function followingFeed(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int)$request->input('limit', 20);

        // 获取用户关注的所有 OA 账号 ID
        $followedAccountIds = OaFollower::where('user_id', $user->id)
            ->pluck('account_id');

        if ($followedAccountIds->isEmpty()) {
            return ApiResponse::success([
                'items' => [],
                'total' => 0,
            ]);
        }

        // 获取这些账号的最新文章
        $articles = \App\Models\OaArticle::whereIn('account_id', $followedAccountIds)
            ->where('status', 'published')
            ->with('account:id,name,slug,avatar,description')
            ->withCount('likes')
            ->withCount('favorites')
            ->withCount('comments')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'summary' => $a->summary,
                'cover_image' => $a->cover_image,
                'published_at' => $a->published_at,
                'created_at' => $a->created_at,
                'likes_count' => $a->likes_count,
                'favorites_count' => $a->favorites_count,
                'comments_count' => $a->comments_count,
                'account' => $a->account ? [
                    'id' => $a->account->id,
                    'name' => $a->account->name,
                    'slug' => $a->account->slug,
                    'avatar' => $a->account->avatar,
                    'description' => $a->account->description,
                ] : null,
            ]);

        return ApiResponse::success([
            'items' => $articles,
            'total' => $articles->count(),
        ]);
    }

    /**
     * 智能收藏夹 — 按类型和标签自动归类
     */
    public function favoriteCollections(Request $request): JsonResponse
    {
        $user = $request->user();

        // 获取所有收藏
        $favorites = Favorite::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($fav) {
                $item = [
                    'type' => $this->typeLabel($fav->favorable_type),
                    'model_type' => $fav->favorable_type,
                    'id' => $fav->favorable_id,
                    'interacted_at' => $fav->created_at,
                ];
                try {
                    $model = $fav->favorable_type::find($fav->favorable_id);
                    if ($model) {
                        $item['title'] = $model->title ?? $model->name ?? '';
                        $item['excerpt'] = $model->excerpt ?? $model->summary ?? '';
                        $item['cover'] = $model->cover_image ?? $model->featured_image ?? $model->image ?? '';
                        $item['tags'] = $model->tags ?? [];
                        $item['slug'] = $model->slug ?? '';
                    }
                } catch (\Throwable $e) {
                    $item['title'] = '(已删除)';
                    $item['tags'] = [];
                }
                return $item;
            });

        // ── 按来源类型分组 ──
        $typeCollections = [
            [
                'key' => 'type_blog',
                'name' => '📝 博客文章',
                'icon' => '📝',
                'type_filter' => 'blog_post',
                'count' => 0,
                'items' => [],
            ],
            [
                'key' => 'type_oa',
                'name' => '📄 OA 文章',
                'icon' => '📄',
                'type_filter' => 'oa_article',
                'count' => 0,
                'items' => [],
            ],
            [
                'key' => 'type_forum',
                'name' => '🌐 广场帖子',
                'icon' => '🌐',
                'type_filter' => 'forum_post',
                'count' => 0,
                'items' => [],
            ],
        ];

        foreach ($favorites as $fav) {
            foreach ($typeCollections as &$col) {
                if ($col['type_filter'] === $fav['type']) {
                    $col['count']++;
                    if (count($col['items']) < 4) {
                        $col['items'][] = $fav;
                    }
                }
            }
        }

        // ── 按标签自动归类（仅对有标签的内容） ──
        $tagGroups = [];
        $tagIconMap = [
            '教程' => '📖', 'tutorial' => '📖', 'guide' => '📖',
            '技术' => '💻', 'tech' => '💻', '开发' => '💻',
            '产品' => '📦', 'product' => '📦', '更新' => '📦',
            '新闻' => '📰', 'news' => '📰', '动态' => '📰',
            '案例' => '🏢', 'case' => '🏢', '客户' => '🏢',
            '设计' => '🎨', 'design' => '🎨', 'ui' => '🎨',
        ];

        foreach ($favorites as $fav) {
            $tags = $fav['tags'] ?? [];
            if (!is_array($tags)) continue;
            foreach ($tags as $tag) {
                $tagKey = mb_strtolower(trim($tag));
                if (!$tagKey) continue;
                if (!isset($tagGroups[$tagKey])) {
                    $tagGroups[$tagKey] = [
                        'key' => 'tag_' . $tagKey,
                        'name' => '# ' . $tag,
                        'icon' => $tagIconMap[$tagKey] ?? '🏷️',
                        'type_filter' => '',
                        'search' => $tag,
                        'count' => 0,
                        'items' => [],
                    ];
                }
                $tagGroups[$tagKey]['count']++;
                if (count($tagGroups[$tagKey]['items']) < 4) {
                    $tagGroups[$tagKey]['items'][] = $fav;
                }
            }
        }

        // 按收藏数排序标签组
        $tagCollections = collect($tagGroups)->sortByDesc('count')->values()->take(8)->toArray();

        return ApiResponse::success([
            'collections' => array_merge($typeCollections, $tagCollections),
            'total' => $favorites->count(),
        ]);
    }

    /**
     * 安全评分面板
     */
    public function securityScore(Request $request): JsonResponse
    {
        $user = $request->user();
        $score = 0;
        $maxScore = 100;
        $items = [];

        // 1. 邮箱已验证 (+20)
        $emailVerified = !is_null($user->email_verified_at);
        if ($emailVerified) $score += 20;
        $items[] = [
            'key' => 'email',
            'label' => '邮箱验证',
            'detail' => $user->email,
            'passed' => $emailVerified,
            'score' => 20,
            'action' => $emailVerified ? null : '验证邮箱',
            'action_url' => null,
        ];

        // 2. 手机已验证 (+20)
        $phoneVerified = !is_null($user->phone_verified_at);
        if ($phoneVerified) $score += 20;
        $items[] = [
            'key' => 'phone',
            'label' => '手机绑定',
            'detail' => $user->phone ? substr_replace($user->phone, '****', 3, 4) : '未绑定',
            'passed' => $phoneVerified,
            'score' => 20,
            'action' => $phoneVerified ? null : '绑定手机',
            'action_url' => '/build/account/profile',
        ];

        // 3. MFA 已开启 (+25)
        $mfaEnabled = (bool)$user->mfa_enabled;
        if ($mfaEnabled) $score += 25;
        $items[] = [
            'key' => 'mfa',
            'label' => 'MFA 双因素认证',
            'detail' => $mfaEnabled ? '已开启' : '未开启',
            'passed' => $mfaEnabled,
            'score' => 25,
            'action' => $mfaEnabled ? null : '立即开启',
            'action_url' => '/build/account/passkey',
        ];

        // 4. 密码强度 (90天内修改过 +20)
        $passwordFresh = false;
        if ($user->password_changed_at) {
            $changedAt = $user->password_changed_at instanceof \Carbon\Carbon
                ? $user->password_changed_at
                : \Carbon\Carbon::parse($user->password_changed_at);
            $passwordFresh = $changedAt->diffInDays(now()) <= 90;
        } else {
            // 从未记录修改时间，假设是旧的
            $passwordFresh = false;
        }
        if ($passwordFresh) $score += 20;
        $items[] = [
            'key' => 'password',
            'label' => '密码时效',
            'detail' => $user->password_changed_at
                ? \Carbon\Carbon::parse($user->password_changed_at)->diffInDays(now()) . ' 天前修改'
                : '未知',
            'passed' => $passwordFresh,
            'score' => 20,
            'action' => $passwordFresh ? null : '修改密码',
            'action_url' => '/build/account/profile',
        ];

        // 5. 近期无异常登录 (+15)
        $loginSafe = ($user->login_attempts ?? 0) < 5 && is_null($user->locked_until);
        if ($loginSafe) $score += 15;
        $items[] = [
            'key' => 'login',
            'label' => '登录安全',
            'detail' => $user->locked_until ? '账户已被锁定' : ($user->login_attempts > 0 ? $user->login_attempts . ' 次失败尝试' : '正常'),
            'passed' => $loginSafe,
            'score' => 15,
            'action' => $loginSafe ? null : '查看登录历史',
            'action_url' => '/build/account/login-history',
        ];

        $level = $score >= 80 ? 'safe' : ($score >= 50 ? 'warning' : 'danger');
        $levelLabel = $score >= 80 ? '安全' : ($score >= 50 ? '需改进' : '不安全');

        return ApiResponse::success([
            'score' => $score,
            'max_score' => $maxScore,
            'level' => $level,
            'level_label' => $levelLabel,
            'items' => $items,
        ]);
    }

    /**
     * 导出用户互动数据
     */
    public function export(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user();
        $format = $request->input('format', 'markdown'); // json | markdown | csv
        $type = $request->input('type', 'all'); // all | follows | favorites | likes

        $data = [];

        // 关注
        if (in_array($type, ['all', 'follows'])) {
            $follows = OfficialAccount::whereHas('followers', fn($q) => $q->where('user_id', $user->id))
                ->select('id', 'name', 'slug', 'description')
                ->get()
                ->map(fn($a) => [
                    '名称' => $a->name,
                    '简介' => $a->description,
                    '关注时间' => OaFollower::where('account_id', $a->id)->where('user_id', $user->id)->value('created_at')?->toDateTimeString(),
                ]);
            $data['关注'] = $follows;
        }

        // 收藏
        if (in_array($type, ['all', 'favorites'])) {
            $favs = Favorite::where('user_id', $user->id)->orderByDesc('created_at')->get()->map(function ($fav) {
                $item = ['类型' => $this->typeLabel($fav->favorable_type), '收藏时间' => $fav->created_at->toDateTimeString()];
                try {
                    $model = $fav->favorable_type::find($fav->favorable_id);
                    if ($model) {
                        $item['标题'] = $model->title ?? $model->name ?? '';
                        $item['摘要'] = $model->excerpt ?? $model->summary ?? '';
                    }
                } catch (\Throwable $e) { $item['标题'] = '(已删除)'; }
                return $item;
            });
            $data['收藏'] = $favs;
        }

        // 点赞
        if (in_array($type, ['all', 'likes'])) {
            $likes = Like::where('user_id', $user->id)->orderByDesc('created_at')->get()->map(function ($like) {
                $item = ['类型' => $this->typeLabel($like->likeable_type), '点赞时间' => $like->created_at->toDateTimeString()];
                try {
                    $model = $like->likeable_type::find($like->likeable_id);
                    if ($model) $item['标题'] = $model->title ?? $model->name ?? '';
                } catch (\Throwable $e) { $item['标题'] = '(已删除)'; }
                return $item;
            });
            $data['点赞'] = $likes;
        }

        // 阅读统计
        $totalRead = \App\Models\BlogRead::where('user_id', $user->id)->count()
            + \App\Models\OaArticleRead::where('user_id', $user->id)->count();
        $data['阅读统计'] = [['总阅读量' => $totalRead, '导出时间' => now()->toDateTimeString()]];

        if ($format === 'json') {
            $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $filename = 'my-data-' . now()->format('Ymd') . '.json';
            $mime = 'application/json';
        } elseif ($format === 'csv') {
            $lines = [];
            foreach ($data as $section => $items) {
                $lines[] = "\n=== {$section} ===\n";
                if (!empty($items) && is_array($items[0] ?? null)) {
                    $lines[] = implode(',', array_keys($items[0])) . "\n";
                    foreach ($items as $item) {
                        $lines[] = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', array_values($item))) . "\n";
                    }
                }
            }
            $content = implode('', $lines);
            $filename = 'my-data-' . now()->format('Ymd') . '.csv';
            $mime = 'text/csv';
        } else {
            // Markdown
            $md = "# 📤 我的互动数据导出\n\n";
            $md .= "> 导出时间：".now()->toDateTimeString()."\n\n";
            foreach ($data as $section => $items) {
                $md .= "## {$section}\n\n";
                if (count($items) === 0) { $md .= "_暂无数据_\n\n"; continue; }
                $md .= "| " . implode(' | ', array_keys($items[0])) . " |\n";
                $md .= "|" . implode('|', array_fill(0, count($items[0]), '---')) . "|\n";
                foreach ($items as $item) {
                    $md .= "| " . implode(' | ', array_map(fn($v) => $v ?? '-', array_values($item))) . " |\n";
                }
                $md .= "\n";
            }
            $md .= "---\n*由 HWT License 自动生成*\n";
            $content = $md;
            $filename = 'my-data-' . now()->format('Ymd') . '.md';
            $mime = 'text/markdown';
        }

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($content),
        ]);
    }

    /**
     * 获取个性化偏好设置
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $user->preferences ?? [];

        $defaults = [
            'theme' => 'system',
            'blog_font' => 'default',
            'blog_font_size' => 'medium',
            'notify_new_article' => true,
            'notify_comment_reply' => true,
            'notify_follow_update' => true,
            'email_digest' => 'weekly',
        ];

        return ApiResponse::success(array_merge($defaults, $prefs));
    }

    /**
     * 保存个性化偏好设置
     */
    public function savePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => 'sometimes|in:system,light,dark,sepia',
            'blog_font' => 'sometimes|in:default,serif,monospace',
            'blog_font_size' => 'sometimes|in:small,medium,large',
            'notify_new_article' => 'sometimes|boolean',
            'notify_comment_reply' => 'sometimes|boolean',
            'notify_follow_update' => 'sometimes|boolean',
            'email_digest' => 'sometimes|in:none,daily,weekly',
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];

        foreach ($validated as $key => $value) {
            $preferences[$key] = $value;
        }

        $user->preferences = $preferences;
        $user->save();

        return ApiResponse::success($preferences, '偏好设置已更新');
    }

    // ─── 阅读清单队列 ───

    /**
     * 获取阅读清单
     */
    public function getReadingQueue(Request $request): JsonResponse
    {
        $user = $request->user();
        $tab = $request->input('tab', 'pending'); // pending | completed | all

        $query = \App\Models\ReadingQueue::where('user_id', $user->id);
        if ($tab === 'pending') $query->where('is_completed', false);
        elseif ($tab === 'completed') $query->where('is_completed', true);

        $items = $query->orderBy('is_completed')->orderBy('sort_order')->orderByDesc('created_at')
            ->get()
            ->map(function ($rq) {
                $item = [
                    'id' => $rq->id,
                    'type' => $this->typeLabel($rq->queueable_type),
                    'model_type' => $rq->queueable_type,
                    'content_id' => $rq->queueable_id,
                    'note' => $rq->note,
                    'sort_order' => $rq->sort_order,
                    'is_completed' => $rq->is_completed,
                    'completed_at' => $rq->completed_at?->toDateTimeString(),
                    'created_at' => $rq->created_at->toDateTimeString(),
                ];
                try {
                    $model = $rq->queueable_type::find($rq->queueable_id);
                    if ($model) {
                        $item['title'] = $model->title ?? $model->name ?? '';
                        $item['excerpt'] = $model->excerpt ?? $model->summary ?? '';
                        $item['cover'] = $model->cover_image ?? $model->featured_image ?? '';
                        $item['slug'] = $model->slug ?? '';
                    }
                } catch (\Throwable $e) {
                    $item['title'] = '(已删除)';
                }
                return $item;
            });

        return ApiResponse::success([
            'items' => $items,
            'pending_count' => $items->where('is_completed', false)->count(),
            'completed_count' => $items->where('is_completed', true)->count(),
        ]);
    }

    /**
     * 检查某项是否在阅读清单中
     */
    public function checkReadingQueueItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'id' => 'required|integer',
        ]);

        $type = $this->resolveModelClass($validated['type']);
        $item = \App\Models\ReadingQueue::where('user_id', $request->user()->id)
            ->where('queueable_type', $type)
            ->where('queueable_id', $validated['id'])
            ->first();

        return ApiResponse::success([
            'in_queue' => $item !== null,
            'item_id' => $item?->id,
            'is_completed' => $item?->is_completed ?? false,
        ]);
    }

    /**
     * 添加到阅读清单
     */
    public function addToReadingQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'id' => 'required|integer',
            'note' => 'nullable|string|max:500',
        ]);

        $type = $this->resolveModelClass($validated['type']);
        $user = $request->user();

        if (\App\Models\ReadingQueue::where('user_id', $user->id)
            ->where('queueable_type', $type)
            ->where('queueable_id', $validated['id'])
            ->exists()
        ) {
            return ApiResponse::error('ALREADY_IN_QUEUE', '已在阅读清单中');
        }

        $maxSort = \App\Models\ReadingQueue::where('user_id', $user->id)
            ->where('is_completed', false)->max('sort_order') ?? 0;

        $item = \App\Models\ReadingQueue::create([
            'user_id' => $user->id,
            'queueable_type' => $type,
            'queueable_id' => $validated['id'],
            'note' => $validated['note'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        return ApiResponse::success(['id' => $item->id], '已添加到阅读清单');
    }

    /**
     * 从阅读清单移除
     */
    public function removeFromReadingQueue(int $id): JsonResponse
    {
        $item = \App\Models\ReadingQueue::where('user_id', auth()->id())->findOrFail($id);
        $item->delete();
        return ApiResponse::success(null, '已从阅读清单移除');
    }

    /**
     * 标记完成/未完成
     */
    public function toggleReadingQueueItem(int $id): JsonResponse
    {
        $item = \App\Models\ReadingQueue::where('user_id', auth()->id())->findOrFail($id);
        $item->is_completed = !$item->is_completed;
        $item->completed_at = $item->is_completed ? now() : null;
        $item->save();
        return ApiResponse::success([
            'is_completed' => $item->is_completed,
        ], $item->is_completed ? '已标记为已完成' : '已移回待读');
    }

    /**
     * 更新排序
     */
    public function sortReadingQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['items'] as $item) {
            \App\Models\ReadingQueue::where('user_id', auth()->id())
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return ApiResponse::success(null, '排序已更新');
    }
}
