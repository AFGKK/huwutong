<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\ApiResponse;
use App\Models\BlogPost;
use App\Models\BlogComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlogCommentController extends Controller
{
    public function __construct()
    {
    }

    public function index($blogId)
    {
        $blog = BlogPost::findOrFail($blogId);
        $comments = BlogComment::with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
            ->where('blog_id', $blogId)
            ->whereNull('parent_id')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return ApiResponse::success($comments);
    }

    // ── 公开评论列表（通过 slug 或 id）──
    public function publicIndex($slug)
    {
        $blog = is_numeric($slug)
            ? BlogPost::where('id', $slug)->where('is_published', true)->firstOrFail()
            : BlogPost::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $comments = BlogComment::with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
            ->where('blog_id', $blog->id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'content' => $c->content,
                'image' => $c->image,
                'likes_count' => $c->likes_count,
                'created_at' => $c->created_at,
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'avatar' => $c->user->avatar, 'avatar_url' => $c->user->avatar_url] : null,
                'replies' => $c->replies->map(fn($r) => [
                    'id' => $r->id,
                    'content' => $r->content,
                    'created_at' => $r->created_at,
                    'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'avatar' => $r->user->avatar, 'avatar_url' => $r->user->avatar_url] : null,
                ]),
            ]);
        return ApiResponse::success($comments);
    }

    // ── 公开发表评论（通过 slug 或 id）──
    public function publicStore(Request $request, $slug)
    {
        $blog = is_numeric($slug)
            ? BlogPost::where('id', $slug)->where('is_published', true)->firstOrFail()
            : BlogPost::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), __('app.common.validation_failed'), 422);
        }
        $comment = BlogComment::create([
            'blog_id' => $blog->id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
        ]);
        $comment->load('user:id,name,avatar');
        return ApiResponse::success([
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at,
            'user' => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name, 'avatar' => $comment->user->avatar] : null,
        ], __('app.blog_comment.comment_posted'), 201);
    }

    public function store(Request $request, $blogId)
    {
        $blog = BlogPost::findOrFail($blogId);
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:blog_comments,id',
            'image' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), __('app.common.validation_failed'), 422);
        }
        $comment = BlogComment::create([
            'blog_id' => $blogId,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'image' => $request->image,
        ]);
        $comment->load('user:id,name,avatar');
        return ApiResponse::success($comment, __("app.blog_comment.msg_7d2cf0c6"), 201);
    }

    public function destroy($blogId, $id)
    {
        $comment = BlogComment::where('blog_id', $blogId)->findOrFail($id);
        if ($comment->user_id !== auth()->id()) {
            return ApiResponse::error(__('app.blog_comment.no_delete_permission'), __("app.blog_comment.msg_6bf3429d"), 403);
        }
        $comment->replies()->delete();
        $comment->delete();
        return ApiResponse::success(null, __("app.blog_comment.msg_5cc23262"));
    }

    public function toggleLike($blogId, $id)
    {
        $comment = BlogComment::where('blog_id', $blogId)->findOrFail($id);
        $comment->increment('likes_count');
        return ApiResponse::success(['likes_count' => $comment->fresh()->likes_count]);
    }

    // ── 通过 ID 查评论（兼容前端使用 post_id）──
    public function publicIndexById($id)
    {
        $blog = BlogPost::where('id', $id)->where('is_published', true)->firstOrFail();
        $comments = BlogComment::with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
            ->where('blog_id', $blog->id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'content' => $c->content,
                'image' => $c->image,
                'likes_count' => $c->likes_count,
                'created_at' => $c->created_at,
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'avatar' => $c->user->avatar, 'avatar_url' => $c->user->avatar_url] : null,
                'replies' => $c->replies->map(fn($r) => [
                    'id' => $r->id,
                    'content' => $r->content,
                    'created_at' => $r->created_at,
                    'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'avatar' => $r->user->avatar, 'avatar_url' => $r->user->avatar_url] : null,
                ]),
            ]);
        return ApiResponse::success($comments);
    }

    // ── 通过 ID 发表评论（兼容前端使用 post_id）──
    public function publicStoreById(Request $request, $id)
    {
        $blog = BlogPost::where('id', $id)->where('is_published', true)->firstOrFail();
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), 'VALIDATION_ERROR', 422);
        }
        $comment = BlogComment::create([
            'blog_id' => $blog->id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
        ]);
        $comment->load('user:id,name,avatar');
        return ApiResponse::success([
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at,
            'user' => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name, 'avatar' => $comment->user->avatar] : null,
        ], __('app.blog_comment.comment_posted'), 201);
    }
}
