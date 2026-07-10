<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\OaArticle;
use App\Models\OaSubmission;
use App\Models\OfficialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 互物号管理后台控制器
 * 从 OfficialAccountController 拆分出的管理相关方法
 */
class OaAdminController extends Controller
{
    // ── 管理后台：文章列表 ──
    public function adminArticles(Request $request): JsonResponse
    {
        $query = OaArticle::with('account:id,name', 'author:id,name')
            ->withCount('comments');

        if ($q = $request->input('q')) {
            $query->where(function($q2) use ($q) {
                $q2->where('title', 'like', "%{$q}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        $pendingSubmissions = null;
        if ($request->input('pending_submissions')) {
            $pendingSubmissions = OaSubmission::with('account:id,name', 'user:id,name')
                ->where('status', 'pending')
                ->orderBy('id', 'desc')
                ->get();
        }

        $articles = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return ApiResponse::success([
            'articles' => $articles->items(),
            'pending_submissions' => $pendingSubmissions,
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'total' => $articles->total(),
                'per_page' => $articles->perPage(),
            ],
        ]);
    }

    // ── 管理后台：文章详情 ──
    public function adminArticleShow(int $id): JsonResponse
    {
        $article = OaArticle::with('account:id,name', 'author:id,name')
            ->withCount('comments')
            ->findOrFail($id);
        return ApiResponse::success($article);
    }

    // ── 管理后台：审核投稿（管理员） ──
    public function adminReviewSubmission(int $id, Request $request): JsonResponse
    {
        $submission = OaSubmission::with('account')->findOrFail($id);
        $action = $request->input('action');

        if ($action === 'approve') {
            $submission->update(['status' => 'approved', 'reviewer_id' => auth()->id(), 'reviewed_at' => now()]);

            $article = OaArticle::create([
                'account_id' => $submission->account_id,
                'author_id' => $submission->user_id,
                'title' => $submission->title,
                'content' => $submission->content,
                'cover_image' => $submission->cover_image,
                'summary' => $submission->summary,
                'status' => 'published',
                'published_at' => now(),
            ]);

            return ApiResponse::success($article, '已审核通过，文章已发布');
        }

        if ($action === 'reject') {
            $submission->update([
                'status' => 'rejected',
                'reviewer_id' => auth()->id(),
                'reviewed_at' => now(),
                'reject_reason' => $request->input('reason', ''),
            ]);
            return ApiResponse::success($submission, '已驳回');
        }

        return ApiResponse::error('INVALID_ACTION', '无效操作', 400);
    }

    // ── 管理后台：删除文章 ──
    public function adminDeleteArticle(int $id): JsonResponse
    {
        $article = OaArticle::findOrFail($id);
        $article->delete();
        return ApiResponse::success(null, '已删除');
    }

    // ── 管理后台：文章状态切换 ──
    public function adminToggleArticleStatus(int $id): JsonResponse
    {
        $article = OaArticle::findOrFail($id);
        $article->status = $article->status === 'published' ? 'draft' : 'published';
        if ($article->status === 'published') {
            $article->published_at = now();
        }
        $article->save();
        return ApiResponse::success(['status' => $article->status], $article->status === 'published' ? '已发布' : '已下架');
    }

    // ── 管理后台：全局置顶 ──
    public function adminPinArticle(int $id): JsonResponse
    {
        $article = OaArticle::findOrFail($id);
        $article->update(['is_global_pinned' => !$article->is_global_pinned]);
        $fresh = $article->fresh();
        return ApiResponse::success(['is_global_pinned' => $fresh->is_global_pinned], $fresh->is_global_pinned ? '已置顶（全局）' : '已取消全局置顶');
    }

    // ── 管理后台：互物号列表 ──
    public function adminIndex(Request $request): JsonResponse
    {
        $query = OfficialAccount::with('owner:id,name,email')
            ->with('category:id,name')
            ->withCount(['followers', 'articles' => fn($q) => $q->where('status', 'published')]);

        if ($q = $request->input('q')) {
            $query->where(function($q2) use ($q) {
                $q2->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }
        if ($request->input('status')) {
            $status = $request->input('status');
            if ($status === 'verified') {
                $query->whereNotNull('verified_at');
            } elseif ($status === 'verify_request') {
                $allAccounts = OfficialAccount::whereNull('verified_at')->get();
                $ids = $allAccounts->filter(function($a) {
                    $vr = $a->settings['verify_request'] ?? null;
                    return $vr && empty($vr['rejected']);
                })->pluck('id')->toArray();
                $query->whereIn('id', $ids);
            } else {
                $query->where('status', $status);
            }
        }
        if ($request->input('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        return ApiResponse::paginated($query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20)));
    }

    // ── 管理后台：互物号详情 ──
    public function adminShow(int $id): JsonResponse
    {
        $account = OfficialAccount::with('owner:id,name,email')
            ->with('category:id,name')
            ->withCount(['followers', 'articles' => fn($q) => $q->where('status', 'published')])
            ->with(['articles' => fn($q) => $q->where('status', 'published')->latest()->limit(10)])
            ->findOrFail($id);
        return ApiResponse::success($account);
    }

    // ── 管理后台：启用/禁用 ──
    public function adminToggleStatus(int $id): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        $account->status = $account->status === 'active' ? 'suspended' : 'active';
        $account->save();
        return ApiResponse::success(['status' => $account->status], $account->status === 'active' ? '已启用' : '已禁用');
    }

    // ── 管理后台：审核通过 ──
    public function adminApprove(int $id): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        if ($account->status !== 'pending') {
            return ApiResponse::error('该互物号不是待审核状态', 422);
        }
        $account->status = 'active';
        $account->save();

        \App\Models\OaFollower::firstOrCreate([
            'followable_id' => $account->id,
            'user_id' => $account->owner_id,
        ]);

        return ApiResponse::success($account, '已审核通过');
    }

    // ── 管理后台：审核拒绝 ──
    public function adminReject(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        if ($account->status !== 'pending') {
            return ApiResponse::error('该互物号不是待审核状态', 422);
        }
        $reason = $request->input('reason', '');
        $account->status = 'rejected';
        $account->save();

        if ($reason) {
            $settings = $account->settings ?? [];
            $settings['reject_reason'] = $reason;
            $account->settings = $settings;
            $account->save();
        }

        return ApiResponse::success($account, '已拒绝');
    }

    // ── 管理后台：删除互物号 ──
    public function adminDestroy(int $id): JsonResponse
    {
        $account = OfficialAccount::withCount('articles')->findOrFail($id);
        if ($account->articles_count > 0) {
            return ApiResponse::error('该互物号下有文章，无法删除', 422);
        }
        $account->delete();
        return ApiResponse::success(null, '已删除');
    }

    // ── 管理后台：编辑互物号 ──
    public function adminUpdate(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        $validated = $request->validate([
            'name' => 'nullable|string|max:50',
            'slug' => 'nullable|string|max:100|unique:official_accounts,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'category_id' => 'nullable|integer|exists:oa_categories,id',
        ]);
        $account->update(array_filter($validated));
        return ApiResponse::success($account->fresh()->load('category:id,name'), '已更新');
    }

    // ── 管理后台：批量启用/禁用 ──
    public function adminBatchToggleStatus(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $status = $request->input('status', 'active');
        if (empty($ids)) return ApiResponse::error('请选择互物号', 400);
        $count = OfficialAccount::whereIn('id', $ids)->update(['status' => $status]);
        return ApiResponse::success(['count' => $count], "已{$count}个互物号" . ($status === 'active' ? '启用' : '禁用'));
    }

    // ── 管理后台：批量删除 ──
    public function adminBatchDelete(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return ApiResponse::error('请选择互物号', 400);
        $accounts = OfficialAccount::withCount('articles')->whereIn('id', $ids)->get();
        $deleted = 0;
        foreach ($accounts as $acc) {
            if ($acc->articles_count === 0) {
                $acc->delete();
                $deleted++;
            }
        }
        $skipped = count($ids) - $deleted;
        return ApiResponse::success(['deleted' => $deleted, 'skipped' => $skipped], "已删除{$deleted}个，{$skipped}个因有文章跳过");
    }
}
