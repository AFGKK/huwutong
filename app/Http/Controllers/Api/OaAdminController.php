<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\OaArticle;
use App\Models\OaCategory;
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

            return ApiResponse::success($article, __('app.api.oa_admin.approved_published'));
        }

        if ($action === 'reject') {
            $submission->update([
                'status' => 'rejected',
                'reviewer_id' => auth()->id(),
                'reviewed_at' => now(),
                'reject_reason' => $request->input('reason', ''),
            ]);
            return ApiResponse::success($submission, __('app.api.oa_admin.rejected'));
        }

        return ApiResponse::error('INVALID_ACTION', __('app.api.oa_admin.invalid_action'), 400);
    }

    // ── 管理后台：删除文章 ──
    public function adminDeleteArticle(int $id): JsonResponse
    {
        $article = OaArticle::findOrFail($id);
        $article->delete();
        return ApiResponse::success(null, __('app.api.oa_admin.deleted'));
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
        return ApiResponse::success(['status' => $article->status], $article->status === 'published' ? __('app.api.oa_admin.published') : __('app.api.oa_admin.unpublished'));
    }

    // ── 管理后台：全局置顶 ──
    public function adminPinArticle(int $id): JsonResponse
    {
        $article = OaArticle::findOrFail($id);
        $article->update(['is_global_pinned' => !$article->is_global_pinned]);
        $fresh = $article->fresh();
        return ApiResponse::success(['is_global_pinned' => $fresh->is_global_pinned], $fresh->is_global_pinned ? __('app.api.oa_admin.global_pinned') : __('app.api.oa_admin.global_unpinned'));
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
        return ApiResponse::success(['status' => $account->status], $account->status === 'active' ? __('app.api.oa_admin.enabled') : __('app.api.oa_admin.disabled'));
    }

    // ── 管理后台：审核通过 ──
    public function adminApprove(int $id): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        if ($account->status !== 'pending') {
            return ApiResponse::error(__('app.api.oa_admin.not_pending'), 422);
        }
        $account->status = 'active';
        $account->save();

        \App\Models\OaFollower::firstOrCreate([
            'followable_id' => $account->id,
            'user_id' => $account->owner_id,
        ]);

        return ApiResponse::success($account, __('app.api.oa_admin.approved'));
    }

    // ── 管理后台：审核拒绝 ──
    public function adminReject(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        if ($account->status !== 'pending') {
            return ApiResponse::error(__('app.api.oa_admin.not_pending'), 422);
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

        return ApiResponse::success($account, __('app.api.oa_admin.account_rejected'));
    }

    // ── 管理后台：删除互物号 ──
    public function adminDestroy(int $id): JsonResponse
    {
        $account = OfficialAccount::withCount('articles')->findOrFail($id);
        if ($account->articles_count > 0) {
            return ApiResponse::error(__('app.api.oa_admin.has_articles'), 422);
        }
        $account->delete();
        return ApiResponse::success(null, __('app.api.oa_admin.deleted'));
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
        return ApiResponse::success($account->fresh()->load('category:id,name'), __('app.api.oa_admin.updated'));
    }

    // ── 管理后台：批量启用/禁用 ──
    public function adminBatchToggleStatus(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $status = $request->input('status', 'active');
        if (empty($ids)) return ApiResponse::error(__('app.api.oa_admin.select_accounts'), 400);
        $count = OfficialAccount::whereIn('id', $ids)->update(['status' => $status]);
        return ApiResponse::success(['count' => $count], __('app.api.oa_admin.bulk_status', ['count' => $count, 'action' => $status === 'active' ? __('app.api.oa_admin.action_enable') : __('app.api.oa_admin.action_disable')]));
    }

    // ── 管理后台：批量删除 ──
    public function adminBatchDelete(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return ApiResponse::error(__('app.api.oa_admin.select_accounts'), 400);
        $accounts = OfficialAccount::withCount('articles')->whereIn('id', $ids)->get();
        $deleted = 0;
        foreach ($accounts as $acc) {
            if ($acc->articles_count === 0) {
                $acc->delete();
                $deleted++;
            }
        }
        $skipped = count($ids) - $deleted;
        return ApiResponse::success(['deleted' => $deleted, 'skipped' => $skipped], __('app.api.oa_admin.bulk_deleted', ['deleted' => $deleted, 'skipped' => $skipped]));
    }

    // ── 管理后台：分类管理 ──
    public function adminCategories(): JsonResponse
    {
        $categories = OaCategory::withCount('accounts')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($categories);
    }

    public function adminStoreCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $category = OaCategory::create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]));

        return ApiResponse::success($category->loadCount('accounts'), __('app.api.oa_admin.created'), 201);
    }

    public function adminUpdateCategory(Request $request, int $id): JsonResponse
    {
        $category = OaCategory::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $category->update($validated);
        return ApiResponse::success($category->fresh()->loadCount('accounts'), __('app.api.oa_admin.updated'));
    }

    public function adminDestroyCategory(int $id): JsonResponse
    {
        $category = OaCategory::findOrFail($id);
        $category->delete();
        return ApiResponse::success(null, __('app.api.oa_admin.deleted'));
    }


    public function adminReviewAppeal(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        $action = $request->input('action');
        $settings = $account->settings ?? [];

        if ($action === 'approve') {
            unset($settings['appeal_reason'], $settings['appealed_at'], $settings['appeal_rejected_reason']);
            $account->update(['status' => 'active', 'settings' => $settings]);
            return ApiResponse::success($account->fresh(), '申诉已通过');
        }

        if ($action === 'reject') {
            $settings['appeal_rejected_reason'] = $request->input('reason', '');
            unset($settings['appeal_reason'], $settings['appealed_at']);
            $account->update(['settings' => $settings]);
            return ApiResponse::success($account->fresh(), '申诉已拒绝');
        }

        return ApiResponse::error('INVALID_ACTION', '无效操作', 400);
    }

    public function adminVerify(int $id): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        if ($account->verified_at) {
            $settings = $account->settings ?? [];
            unset($settings['verified_info']);
            $account->update([
                'verified_at' => null,
                'verified_by' => null,
                'settings' => $settings,
            ]);
            return ApiResponse::success($account->fresh(), '已取消认证');
        }

        $settings = $account->settings ?? [];
        $settings['verified_info'] = $settings['verify_request'] ?? [
            'type' => 'enterprise',
            'name' => $account->name,
        ];
        unset($settings['verify_request']);
        $account->update([
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'settings' => $settings,
        ]);

        return ApiResponse::success($account->fresh(), '已认证');
    }

    public function adminReviewVerify(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        $action = $request->input('action');
        $settings = $account->settings ?? [];

        if ($action === 'approve') {
            $req = $settings['verify_request'] ?? [];
            $settings['verified_info'] = [
                'type' => $req['type'] ?? 'enterprise',
                'name' => $req['name'] ?? $account->name,
            ];
            unset($settings['verify_request']);
            $account->update([
                'verified_at' => now(),
                'verified_by' => auth()->id(),
                'settings' => $settings,
            ]);
            return ApiResponse::success($account->fresh(), '认证已通过');
        }

        if ($action === 'reject') {
            if (isset($settings['verify_request'])) {
                $settings['verify_request']['rejected'] = true;
                $settings['verify_request']['reject_reason'] = $request->input('reason', '');
            }
            $account->update(['settings' => $settings]);
            return ApiResponse::success($account->fresh(), '认证已拒绝');
        }

        return ApiResponse::error('INVALID_ACTION', '无效操作', 400);
    }

}
