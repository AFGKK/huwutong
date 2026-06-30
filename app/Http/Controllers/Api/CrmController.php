<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CrmController extends Controller
{
    public function __construct(
        protected CrmService $crmService,
    ) {}

    protected function ensureAdmin(): void
    {
        if (Gate::denies('admin')) {
            abort(403, '需要管理员权限');
        }
    }

    /**
     * CRM 仪表盘数据
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureAdmin();
        $tenantId = $request->user()->tenant_id;

        $data = $this->crmService->getDashboardData($tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // ============ 客户分群管理 ============

    /**
     * 分群列表
     */
    public function segments(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $segments = CustomerSegment::withCount('customers as member_count')
            ->orderBy('member_count', 'desc')
            ->paginate($request->input('per_page', 20));

        $segments->getCollection()->transform(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'description' => $s->description,
            'rules' => $s->rules,
            'color' => $s->color,
            'icon' => $s->icon,
            'is_dynamic' => $s->is_dynamic,
            'is_active' => $s->is_active,
            'member_count' => $s->customers_count ?? $s->member_count,
            'created_at' => $s->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $segments,
        ]);
    }

    /**
     * 创建分群
     */
    public function storeSegment(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:customer_segments,slug',
            'description' => 'nullable|string|max:500',
            'rules' => 'nullable|array',
            'rules.type' => 'nullable|in:individual,enterprise',
            'rules.level' => 'nullable|in:free,pro,enterprise',
            'rules.status' => 'nullable|in:active,inactive,suspended',
            'rules.min_subscriptions' => 'nullable|integer|min:0',
            'rules.max_subscriptions' => 'nullable|integer|min:0',
            'rules.tags' => 'nullable|array',
            'rules.tags.*' => 'string',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'is_dynamic' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $segment = CustomerSegment::create($validated);

        // 如果是动态分群，立即计算成员
        if ($segment->is_dynamic && $segment->rules) {
            $this->crmService->refreshSegment($segment, $request->user()->tenant_id);
        }

        return response()->json([
            'success' => true,
            'message' => '分群创建成功',
            'data' => $segment,
        ], 201);
    }

    /**
     * 更新分群
     */
    public function updateSegment(Request $request, CustomerSegment $customerSegment): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'rules' => 'nullable|array',
            'rules.type' => 'nullable|in:individual,enterprise',
            'rules.level' => 'nullable|in:free,pro,enterprise',
            'rules.status' => 'nullable|in:active,inactive,suspended',
            'rules.min_subscriptions' => 'nullable|integer|min:0',
            'rules.max_subscriptions' => 'nullable|integer|min:0',
            'rules.tags' => 'nullable|array',
            'rules.tags.*' => 'string',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'is_dynamic' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $customerSegment->update($validated);

        // 重新计算成员
        if ($customerSegment->is_dynamic && $customerSegment->rules) {
            $this->crmService->refreshSegment($customerSegment, $request->user()->tenant_id);
        }

        return response()->json([
            'success' => true,
            'message' => '分群更新成功',
            'data' => $customerSegment->fresh(),
        ]);
    }

    /**
     * 删除分群
     */
    public function destroySegment(CustomerSegment $customerSegment): JsonResponse
    {
        $this->ensureAdmin();
        $customerSegment->customers()->detach();
        $customerSegment->delete();

        return response()->json([
            'success' => true,
            'message' => '分群已删除',
        ]);
    }

    /**
     * 刷新分群成员
     */
    public function refreshSegment(CustomerSegment $customerSegment, Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $count = $this->crmService->refreshSegment($customerSegment, $request->user()->tenant_id);

        return response()->json([
            'success' => true,
            'message' => "分群已刷新，成员数: {$count}",
            'data' => ['member_count' => $count],
        ]);
    }

    /**
     * 分群内的客户列表
     */
    public function segmentCustomers(CustomerSegment $customerSegment, Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $customers = $customerSegment->customers()
            ->with('user')
            ->paginate($request->input('per_page', 20));

        $customers->getCollection()->transform(fn($c) => [
            'id' => $c->id,
            'name' => $c->user?->name ?? 'N/A',
            'email' => $c->user?->email ?? '',
            'phone' => $c->user?->phone ?? '',
            'type' => $c->type,
            'level' => $c->level,
            'status' => $c->status,
            'created_at' => $c->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * 手动分配客户到分群
     */
    public function assignSegment(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'segment_id' => 'required|integer|exists:customer_segments,id',
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'integer|exists:customers,id',
        ]);

        $segment = CustomerSegment::findOrFail($validated['segment_id']);
        $segment->customers()->syncWithoutDetaching($validated['customer_ids']);
        $segment->increment('member_count', count($validated['customer_ids']));

        return response()->json([
            'success' => true,
            'message' => '客户已添加到分群',
        ]);
    }

    /**
     * 从分群移除客户
     */
    public function removeSegmentCustomer(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'segment_id' => 'required|integer|exists:customer_segments,id',
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'integer|exists:customers,id',
        ]);

        $segment = CustomerSegment::findOrFail($validated['segment_id']);
        $segment->customers()->detach($validated['customer_ids']);
        $segment->decrement('member_count', count($validated['customer_ids']));

        return response()->json([
            'success' => true,
            'message' => '客户已从分群移除',
        ]);
    }

    // ============ RFM 分析 ============

    /**
     * RFM 评分列表
     */
    public function rfmScores(Request $request): JsonResponse
    {
        $this->ensureAdmin();
        $tenantId = $request->user()->tenant_id;

        $query = \App\Models\RfmScore::with('customer.user')
            ->where('tenant_id', $tenantId);

        // 按 RFM 分群筛选
        if ($segment = $request->input('rfm_segment')) {
            $query->where('rfm_segment', $segment);
        }

        $scores = $query->orderBy('rfm_total', 'desc')
            ->paginate($request->input('per_page', 20));

        $scores->getCollection()->transform(fn($s) => [
            'id' => $s->id,
            'customer_id' => $s->customer_id,
            'customer_name' => $s->customer?->user?->name ?? 'N/A',
            'recency_days' => $s->recency_days,
            'recency_score' => $s->recency_score,
            'frequency_count' => $s->frequency_count,
            'frequency_score' => $s->frequency_score,
            'monetary_total' => $s->monetary_total,
            'monetary_score' => $s->monetary_score,
            'rfm_total' => $s->rfm_total,
            'rfm_segment' => $s->rfm_segment,
            'calculated_at' => $s->calculated_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $scores,
        ]);
    }

    /**
     * 触发 RFM 重算
     */
    public function recalculateRfm(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $result = $this->crmService->recalculateAllRfm($request->user()->tenant_id);

        return response()->json([
            'success' => true,
            'message' => "RFM 评分已更新，处理 {$result['processed']} 个客户",
            'data' => $result,
        ]);
    }

    // ============ 流失预测 ============

    /**
     * 流失预测列表
     */
    public function churnPredictions(Request $request): JsonResponse
    {
        $this->ensureAdmin();
        $tenantId = $request->user()->tenant_id;

        $query = \App\Models\ChurnPrediction::with('customer.user')
            ->where('tenant_id', $tenantId);

        // 按风险等级筛选
        if ($risk = $request->input('churn_risk')) {
            $query->where('churn_risk', $risk);
        }

        $predictions = $query->orderBy('churn_score', 'desc')
            ->paginate($request->input('per_page', 20));

        $predictions->getCollection()->transform(fn($p) => [
            'id' => $p->id,
            'customer_id' => $p->customer_id,
            'customer_name' => $p->customer?->user?->name ?? 'N/A',
            'customer_email' => $p->customer?->user?->email ?? '',
            'churn_score' => $p->churn_score,
            'churn_risk' => $p->churn_risk,
            'predicted_churn_date' => $p->predicted_churn_date?->toDateString(),
            'signals' => $p->signals,
            'recommended_action' => $p->recommended_action,
            'predicted_at' => $p->predicted_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $predictions,
        ]);
    }

    /**
     * 触发流失预测重算
     */
    public function recalculateChurn(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $result = $this->crmService->predictAllChurn($request->user()->tenant_id);

        return response()->json([
            'success' => true,
            'message' => "流失预测已更新，处理 {$result['processed']} 个客户",
            'data' => $result,
        ]);
    }
}
