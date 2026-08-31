<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\RssFeed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogService
{
    const TYPES = ['blog', 'changelog', 'release_note'];

    // ─── CRUD ───

    public function listPosts(array $filters = [], int $perPage = 20)
    {
        $query = BlogPost::with('category')->orderByDesc('created_at');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('is_published', $filters['status'] === 'published');
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                  ->orWhere('content', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        return $query->paginate($perPage);
    }

    public function getPost(int $id): BlogPost
    {
        return BlogPost::findOrFail($id);
    }

    public function getPostBySlug(string $slug): BlogPost
    {
        return BlogPost::published()->where('slug', $slug)->with('authorUser:id,name,avatar')->firstOrFail();
    }

    public function createPost(array $data): BlogPost
    {
        return DB::transaction(function () use ($data) {
            $post = BlogPost::create($data);
            return $post->fresh();
        });
    }

    public function updatePost(BlogPost $post, array $data): BlogPost
    {
        return DB::transaction(function () use ($post, $data) {
            $post->update($data);
            return $post->fresh();
        });
    }

    public function deletePost(BlogPost $post): bool
    {
        return $post->delete();
    }

    public function togglePublish(BlogPost $post): BlogPost
    {
        $wasPublished = $post->is_published;
        $post->update([
            'is_published' => !$wasPublished,
            'published_at' => !$wasPublished ? now() : null,
        ]);
        return $post->fresh();
    }

    public function toggleFeatured(BlogPost $post): BlogPost
    {
        $post->update(['is_featured' => !$post->is_featured]);
        return $post->fresh();
    }

    // ─── 公开API ───

    public function getPublishedPosts(string $type = null, int $limit = 10, ?int $categoryId = null)
    {
        $query = BlogPost::published()->with('category')->with('authorUser:id,name,avatar')
            ->withCount('likes')
            ->orderByDesc('published_at');

        if ($type) {
            $query->where('type', $type);
        }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->limit($limit)->get();
    }

    public function getLatestChangelog()
    {
        return BlogPost::published()->ofType('changelog')
            ->with('category')
            ->with('authorUser:id,name,avatar')
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();
    }

    /**
     * 获取公开分类列表（含文章数）
     */
    public function getPublicCategories()
    {
        return \App\Models\BlogCategory::active()->ordered()
            ->withCount('publishedPosts')
            ->get();
    }

    public function getFeaturedPosts(int $limit = 3)
    {
        return BlogPost::published()->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function getRelatedPosts(int $id, int $limit = 3)
    {
        $post = BlogPost::findOrFail($id);
        return BlogPost::published()
            ->where('id', '!=', $id)
            ->where(function ($q) use ($post) {
                if ($post->category_id) {
                    $q->where('category_id', $post->category_id);
                }
                if ($post->tags) {
                    $tags = is_string($post->tags) ? json_decode($post->tags, true) : $post->tags;
                    if (is_array($tags) && count($tags) > 0) {
                        foreach ($tags as $tag) {
                            $q->orWhere('tags', 'like', '%"' . $tag . '"%');
                        }
                    }
                }
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    // ─── RSS ───

    public function generateRss(string $feedType = 'all'): string
    {
        $feed = RssFeed::where('feed_type', $feedType)->first();

        $siteUrl = config('app.url', 'https://88.huwutong.com');
        $feedTitle = $feed?->title ?? ($feedType === 'blog' ? '开发者博客' : ($feedType === 'changelog' ? '产品更新日志' : '最新动态'));
        $feedDesc = $feed?->description ?? '互物通平台最新动态与产品更新';
        $language = $feed?->language ?? 'zh-CN';
        $ttl = $feed?->ttl ?? '60';

        $postsQuery = BlogPost::published()->orderByDesc('published_at');
        if ($feedType !== 'all') {
            $postsQuery->where('type', $feedType);
        }
        $posts = $postsQuery->limit(50)->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= "  <title>{$feedTitle}</title>\n";
        $xml .= "  <link>{$siteUrl}</link>\n";
        $xml .= "  <description>{$feedDesc}</description>\n";
        $xml .= "  <language>{$language}</language>\n";
        $xml .= "  <ttl>{$ttl}</ttl>\n";
        $xml .= "  <atom:link href=\"{$siteUrl}/rss/{$feedType}\" rel=\"self\" type=\"application/rss+xml\"/>\n";
        $xml .= "  <lastBuildDate>" . now()->format('r') . "</lastBuildDate>\n";

        foreach ($posts as $post) {
            $postUrl = "{$siteUrl}/blog/{$post->slug}";
            $xml .= "  <item>\n";
            $xml .= "    <title><![CDATA[{$post->title}]]></title>\n";
            $xml .= "    <link>{$postUrl}</link>\n";
            $xml .= "    <guid isPermaLink=\"true\">{$postUrl}</guid>\n";
            $xml .= "    <pubDate>" . $post->published_at->format('r') . "</pubDate>\n";
            $xml .= "    <author><![CDATA[{$post->author}]]></author>\n";
            if ($post->excerpt) {
                $xml .= "    <description><![CDATA[{$post->excerpt}]]></description>\n";
            }
            if ($post->tags) {
                foreach ($post->tags as $tag) {
                    $xml .= "    <category><![CDATA[{$tag}]]></category>\n";
                }
            }
            $xml .= "    <content:encoded><![CDATA[{$post->content}]]></content:encoded>\n";
            $xml .= "  </item>\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return $xml;
    }

    // ─── 统计 ───

    public function getStats(): array
    {
        $base = BlogPost::query();

        return [
            'total' => (clone $base)->count(),
            'published' => (clone $base)->published()->count(),
            'drafts' => (clone $base)->where('is_published', false)->count(),
            'by_type' => [
                'blog' => (clone $base)->ofType('blog')->count(),
                'changelog' => (clone $base)->ofType('changelog')->count(),
                'release_note' => (clone $base)->ofType('release_note')->count(),
            ],
        ];
    }

    /**
     * 检查slug是否可用
     */
    public function checkSlug(string $slug, ?int $excludeId = null): bool
    {
        $query = BlogPost::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return !$query->exists();
    }

    /**
     * 生成唯一slug
     */
    public function generateSlug(string $title): string
    {
        $slug = Str::slug($title);
        $base = $slug;
        $counter = 1;
        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }
        return $slug;
    }

    // ─── 邮件订阅 (M3-57) ───

    /**
     * 创建订阅
     */
    public function createSubscription(array $data): \App\Models\BlogSubscription
    {
        $data['token'] = Str::random(64);

        return \App\Models\BlogSubscription::create($data);
    }

    /**
     * 验证订阅
     */
    public function verifySubscription(string $token): array
    {
        $sub = \App\Models\BlogSubscription::where('token', $token)
            ->whereNull('verified_at')
            ->first();

        if (!$sub) {
            return ['success' => false, 'message' => __('app.common.link_expired_or_verified')];
        }

        $sub->update(['verified_at' => now()]);

        return ['success' => true, 'message' => __('app.common.subscription_confirmed'), 'email' => $sub->email];
    }

    /**
     * 取消订阅
     */
    public function unsubscribe(string $token): array
    {
        $sub = \App\Models\BlogSubscription::where('token', $token)
            ->whereNotNull('verified_at')
            ->whereNull('unsubscribed_at')
            ->first();

        if (!$sub) {
            return ['success' => false, 'message' => __('app.common.subscription_not_found_or_cancelled')];
        }

        $sub->update(['unsubscribed_at' => now()]);

        return ['success' => true, 'message' => __('app.common.subscription_cancelled'), 'email' => $sub->email];
    }

    /**
     * 发布后通知订阅者
     */
    public function notifySubscribers(BlogPost $post): int
    {
        $subscribers = \App\Models\BlogSubscription::verified()
            ->subscribesTo($post->type)
            ->get();

        $count = 0;
        foreach ($subscribers as $sub) {
            // 发送邮件通知 (简化实现，实际应使用队列)
            try {
                \Illuminate\Support\Facades\Mail::to($sub->email)->send(
                    new \App\Mail\BlogPostPublished($post, $sub)
                );
                $count++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Blog notification failed', [
                    'email' => $sub->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * 订阅统计
     */
    public function getSubscriptionStats(): array
    {
        $total = \App\Models\BlogSubscription::count();
        $verified = \App\Models\BlogSubscription::verified()->count();
        $byType = [
            'blog' => \App\Models\BlogSubscription::verified()->subscribesTo('blog')->count(),
            'changelog' => \App\Models\BlogSubscription::verified()->subscribesTo('changelog')->count(),
            'release_note' => \App\Models\BlogSubscription::verified()->subscribesTo('release_note')->count(),
        ];

        return [
            'total' => $total,
            'verified' => $verified,
            'unverified' => $total - $verified,
            'unsubscribed' => \App\Models\BlogSubscription::whereNotNull('unsubscribed_at')->count(),
            'by_type' => $byType,
        ];
    }
}
