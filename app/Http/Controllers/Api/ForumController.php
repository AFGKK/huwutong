<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    // ── 帖子列表 ──
    public function index(Request $request): JsonResponse
    {
        $query = ForumPost::with('user:id,name,avatar')
            ->withCount('replies')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc');

        if ($catId = $request->input('category_id')) {
            $query->where('category_id', $catId);
        }
        if ($q = $request->input('q')) {
            $query->where(function($qry) use ($q) {
                $qry->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        return ApiResponse::paginated($query->paginate(20));
    }

    // ── 帖子详情 ──
    public function show(int $id): JsonResponse
    {
        $post = ForumPost::with(['user:id,name,avatar', 'category', 'replies.user:id,name,avatar'])
            ->withCount('replies')
            ->findOrFail($id);

        $post->increment('views_count');

        $myId = auth()->id();
        $post->setAttribute('is_liked', $myId ? \App\Models\Like::where('user_id', $myId)
            ->where('likeable_type', 'App\\Models\\ForumPost')
            ->where('likeable_id', $id)->exists() : false);

        return ApiResponse::success($post);
    }

    // ── 发布帖子 ──
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:50000',
            'category_id' => 'nullable|integer|exists:forum_categories,id',
        ]);

        $post = ForumPost::create([
            'category_id' => $validated['category_id'] ?? null,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return ApiResponse::success($post->load('user:id,name,avatar'), '帖子已发布', 201);
    }

    // ── 回复帖子 ──
    public function reply(int $postId, Request $request): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string|max:10000']);
        $post = ForumPost::findOrFail($postId);

        if ($post->is_locked) {
            return ApiResponse::error('LOCKED', '帖子已锁定，无法回复', 403);
        }

        $reply = ForumReply::create([
            'post_id' => $postId,
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        $post->increment('replies_count');

        return ApiResponse::success($reply->load('user:id,name,avatar'), '回复已发布', 201);
    }

    // ── 点赞/取消点赞（统一 Like 表）──
    public function toggleLike(int $postId): JsonResponse
    {
        $post = ForumPost::findOrFail($postId);
        $myId = auth()->id();
        $type = 'App\\Models\\ForumPost';

        $existing = \App\Models\Like::where('user_id', $myId)
            ->where('likeable_type', $type)
            ->where('likeable_id', $postId)->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('likes_count');
            return ApiResponse::success(['liked' => false], '已取消点赞');
        }

        \App\Models\Like::create([
            'user_id' => $myId,
            'likeable_type' => $type,
            'likeable_id' => $postId,
        ]);
        $post->increment('likes_count');

        return ApiResponse::success(['liked' => true], '已点赞');
    }

    // ── 删除帖子 ──
    public function destroy(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        if ($post->user_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '只能删除自己的帖子', 403);
        }
        $post->replies()->delete();
        $post->delete();
        return ApiResponse::success(null, '帖子已删除');
    }

    // ── 分类列表 ──
    public function categories(): JsonResponse
    {
        return ApiResponse::success(ForumCategory::orderBy('sort_order')->get());
    }
}
